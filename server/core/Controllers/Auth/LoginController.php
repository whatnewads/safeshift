<?php
/**
 * Login Controller
 * 
 * Handles authentication HTTP requests
 */

namespace App\Controllers\Auth;

use App\Controllers\BaseController;
use Core\ApiResponse;
use Core\Session;

class LoginController extends BaseController
{
    /**
     * Handle login request
     * POST /api/login or /app_login/
     */
    public function login(): void
    {
        // Require POST method
        $this->requireMethod('POST');
        
        // Validate CSRF token
        $this->requireCsrf();
        
        // Validate required fields
        $errors = $this->validateRequired(['username', 'password']);
        if (!empty($errors)) {
            ApiResponse::validationError($errors);
        }
        
        // Get input
        $username = $this->sanitizeString($this->input('username'));
        $password = $this->input('password');
        
        // Attempt login
        $result = $this->authService->login($username, $password);
        
        // Handle response
        if ($result['success']) {
            if ($result['data']['stage'] === 'otp') {
                // 2FA required - redirect to 2FA page
                ApiResponse::success([
                    'stage' => 'otp',
                    'redirect' => '/app_login/2fa.php',
                    'message' => $result['data']['message']
                ]);
            } else {
                // Login complete - redirect to dashboard
                ApiResponse::success([
                    'stage' => 'complete',
                    'redirect' => $result['data']['dashboard_url']
                ]);
            }
        } else {
            ApiResponse::unauthorized($result['message'] ?? 'Invalid credentials');
        }
    }
    
    /**
     * Handle 2FA verification
     * POST /api/verify-2fa or /app_login/2fa.php
     */
    public function verify2FA(): void
    {
        // Require POST method
        $this->requireMethod('POST');
        
        // Validate CSRF token
        $this->requireCsrf();
        
        // Check for pending 2FA
        if (!Session::getPending2FA()) {
            ApiResponse::error('No pending verification. Please login again.', 400);
        }
        
        // Validate required fields
        $errors = $this->validateRequired(['otp_code']);
        if (!empty($errors)) {
            ApiResponse::validationError($errors);
        }
        
        // Get OTP code
        $otpCode = $this->sanitizeString($this->input('otp_code'));
        
        // Remove any spaces or dashes
        $otpCode = str_replace([' ', '-'], '', $otpCode);
        
        // Verify OTP
        $result = $this->authService->verify2FA($otpCode);
        
        // Handle response
        if ($result['success']) {
            // Get redirect URL
            $redirectUrl = Session::get('redirect_after_login', $result['data']['dashboard_url']);
            Session::remove('redirect_after_login');
            
            ApiResponse::success([
                'redirect' => $redirectUrl,
                'message' => 'Login successful'
            ]);
        } else {
            ApiResponse::error($result['message'] ?? 'Invalid verification code', 400);
        }
    }
    
    /**
     * Handle logout request
     * POST /api/logout
     */
    public function logout(): void
    {
        // Any method allowed for logout
        
        // Log out
        $this->authService->logout();
        
        // Return success
        ApiResponse::success([
            'redirect' => '/login',
            'message' => 'Logged out successfully'
        ]);
    }
    
    /**
     * Handle resend OTP request
     * POST /api/resend-otp
     */
    public function resendOtp(): void
    {
        // Require POST method
        $this->requireMethod('POST');
        
        // Validate CSRF token
        $this->requireCsrf();
        
        // Resend OTP
        $result = $this->authService->resendOtp();
        
        // Handle response
        if ($result['success']) {
            ApiResponse::success($result['data']);
        } else {
            ApiResponse::error($result['message'] ?? 'Failed to resend code', 400);
        }
    }
    
    /**
     * Check authentication status
     * GET /api/auth/check
     */
    public function checkAuth(): void
    {
        // Require GET method
        $this->requireMethod('GET');
        
        if ($this->authService->isLoggedIn()) {
            $user = $this->authService->getCurrentUser();
            $role = $this->authService->getPrimaryRole();
            
            ApiResponse::success([
                'authenticated' => true,
                'user' => [
                    'user_id' => $user['user_id'],
                    'username' => $user['username'],
                    'email' => $user['email'],
                    'role' => $role
                ]
            ]);
        } else {
            ApiResponse::success([
                'authenticated' => false
            ]);
        }
    }
    
    /**
     * Get CSRF token
     * GET /api/csrf-token
     */
    public function getCsrfToken(): void
    {
        // Require GET method
        $this->requireMethod('GET');
        
        ApiResponse::success([
            'csrf_token' => $this->authService->getCsrfToken()
        ]);
    }
    
    /**
     * Handle form-based login (for legacy support)
     * This method handles traditional form POST from login.php
     */
    public function handleFormLogin(): void
    {
        // Require POST method
        $this->requireMethod('POST');
        
        // Validate CSRF token
        if (!$this->validateCsrf()) {
            $_SESSION['login_error'] = 'Invalid security token. Please try again.';
            header('Location: /login');
            exit;
        }
        
        // Get input
        $username = $this->sanitizeString($this->input('username', ''));
        $password = $this->input('password', '');
        
        // Validate input
        if (empty($username) || empty($password)) {
            $_SESSION['login_error'] = 'Please enter both username and password.';
            header('Location: /login');
            exit;
        }
        
        // Attempt login
        $result = $this->authService->login($username, $password);
        
        // Handle response
        if ($result['success']) {
            if ($result['data']['stage'] === 'otp') {
                // 2FA required - redirect to 2FA page
                header('Location: /app_login/2fa.php');
            } else {
                // Login complete - redirect to dashboard
                $redirectUrl = Session::get('redirect_after_login', $result['data']['dashboard_url']);
                Session::remove('redirect_after_login');
                header('Location: ' . $redirectUrl);
            }
            exit;
        } else {
            $_SESSION['login_error'] = $result['message'] ?? 'Invalid username or password.';
            header('Location: /login');
            exit;
        }
    }
    
    /**
     * Handle form-based 2FA verification (for legacy support)
     * This method handles traditional form POST from 2fa.php
     */
    public function handleForm2FA(): void
    {
        // Require POST method
        $this->requireMethod('POST');
        
        // Validate CSRF token
        if (!$this->validateCsrf()) {
            $_SESSION['otp_error'] = 'Invalid security token. Please try again.';
            header('Location: /app_login/2fa.php');
            exit;
        }
        
        // Check for pending 2FA
        if (!Session::getPending2FA()) {
            $_SESSION['login_error'] = 'Session expired. Please login again.';
            header('Location: /login');
            exit;
        }
        
        // Get OTP code
        $otpCode = $this->sanitizeString($this->input('otp_code', ''));
        
        if (empty($otpCode)) {
            $_SESSION['otp_error'] = 'Please enter the verification code.';
            header('Location: /app_login/2fa.php');
            exit;
        }
        
        // Remove any spaces or dashes
        $otpCode = str_replace([' ', '-'], '', $otpCode);
        
        // Verify OTP
        $result = $this->authService->verify2FA($otpCode);
        
        // Handle response
        if ($result['success']) {
            // Get redirect URL
            $redirectUrl = Session::get('redirect_after_login', $result['data']['dashboard_url']);
            Session::remove('redirect_after_login');
            header('Location: ' . $redirectUrl);
            exit;
        } else {
            $_SESSION['otp_error'] = $result['message'] ?? 'Invalid verification code.';
            header('Location: /app_login/2fa.php');
            exit;
        }
    }
}