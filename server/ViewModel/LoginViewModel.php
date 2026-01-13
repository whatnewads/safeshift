<?php
/**
 * Login ViewModel
 * 
 * Coordinates login workflow and prepares data for view
 * Delegates business logic to AuthService
 */

namespace ViewModel;

use Core\Services\AuthService;
use Core\Services\AuditService;
use Core\Validators\LoginValidator;
use App\Core\Session;

class LoginViewModel
{
    private AuthService $authService;
    private AuditService $auditService;
    private LoginValidator $validator;
    
    public function __construct()
    {
        $this->authService = new AuthService();
        $this->auditService = new AuditService();
        $this->validator = new LoginValidator();
    }
    
    /**
     * Handle login form submission
     * 
     * @param array $input Sanitized input from router (username, password, csrf_token)
     * @return array Response with success status, messages, and redirect info
     */
    public function handleLogin(array $input): array
    {
        // Validate input format
        $validationErrors = $this->validator->validateLoginInput($input);
        
        if (!empty($validationErrors)) {
            return [
                'success' => false,
                'errors' => $validationErrors,
                'error' => 'Please correct the errors below.'
            ];
        }
        
        // Get sanitized credentials
        $username = $this->validator->sanitizeUsername($input['username']);
        $password = $input['password']; // Never trim or sanitize passwords
        
        // Attempt authentication
        $authResult = $this->authService->login($username, $password);
        
        if ($authResult['success']) {
            // Check if 2FA is required
            if (isset($authResult['data']['stage']) && $authResult['data']['stage'] === 'otp') {
                return [
                    'success' => true,
                    'requires_2fa' => true,
                    'message' => $authResult['data']['message'] ?? 'Verification code sent to your email',
                    'redirect' => null
                ];
            } else {
                // Login complete without 2FA
                return [
                    'success' => true,
                    'requires_2fa' => false,
                    'message' => 'Login successful',
                    'redirect' => $authResult['data']['dashboard_url'] ?? $this->getDefaultRedirectUrl()
                ];
            }
        } else {
            // Login failed
            // Check if account is locked
            $attemptsRemaining = null;
            if (strpos($authResult['message'], 'locked') !== false) {
                $attemptsRemaining = 0;
            }
            
            return [
                'success' => false,
                'error' => $authResult['message'] ?? 'Invalid username or password',
                'attempts_remaining' => $attemptsRemaining
            ];
        }
    }
    
    /**
     * Get login page data
     *
     * @return array Data for login view
     */
    public function getLoginPageData(): array
    {
        // Standard view data for header/footer
        $standardViewData = [
            'currentUser' => null, // Not logged in on login page
            'csrf_token' => $this->authService->getCsrfToken(),
            'pageTitle' => 'Login - SafeShift EHR',
            'pageDescription' => 'Secure login to SafeShift EHR - HIPAA-compliant Occupational Health Electronic Health Records',
            'bodyClass' => 'login-page',
            'additionalCSS' => ['/View/assets/css/login.css'],
            'additionalJS' => ['/View/assets/js/login.js']
        ];
        
        // Login-specific view data
        $loginData = [
            'show_forgot_password' => true,
            'show_remember_me' => false, // Not implemented for security
            'lockout_minutes' => 30,
            'max_attempts' => 5
        ];
        
        // Merge both arrays
        return array_merge($standardViewData, $loginData);
    }
    
    /**
     * Determine redirect URL based on user role
     * 
     * @param string $role User's primary role
     * @return string Dashboard URL
     */
    public function getRedirectUrl(string $role): string
    {
        // Map roles to dashboard URLs
        $roleMap = [
            'tadmin' => '/dashboards/tadmin',
            'cadmin' => '/dashboards/cadmin',
            'pclinician' => '/dashboards/pclinician',
            'dclinician' => '/dashboards/dclinician',
            '1clinician' => '/dashboards/1clinician',
            'custom' => '/dashboards/custom',
            'employee' => '/dashboards/employee',
            'employer' => '/dashboards/employer'
        ];
        
        return $roleMap[strtolower($role)] ?? $this->getDefaultRedirectUrl();
    }
    
    /**
     * Get default redirect URL
     * 
     * @return string
     */
    private function getDefaultRedirectUrl(): string
    {
        return '/dashboard';
    }
    
    /**
     * Check if user is already logged in
     * 
     * @return array|null User info with redirect URL if logged in, null otherwise
     */
    public function checkExistingLogin(): ?array
    {
        if ($this->authService->isLoggedIn()) {
            $user = $this->authService->getCurrentUser();
            $role = $this->authService->getPrimaryRole();
            
            return [
                'user' => $user,
                'redirect' => $role ? $this->getRedirectUrl($role['slug']) : $this->getDefaultRedirectUrl()
            ];
        }
        
        return null;
    }
    
    /**
     * Handle logout request
     * 
     * @return array
     */
    public function handleLogout(): array
    {
        $this->authService->logout();
        
        return [
            'success' => true,
            'message' => 'You have been logged out successfully',
            'redirect' => '/login'
        ];
    }
}