<?php
/**
 * Authentication Functions - REFACTORED VERSION
 * 
 * This file provides backward compatibility while using the new layered architecture
 * Delegates all operations to appropriate services and repositories
 */

namespace App\auth;

use Core\Services\AuthService;
use Core\Services\AuditService;
use App\Core\Session;
use App\Helpers\UuidHelper;
use Exception;

// Initialize services (singleton pattern for backward compatibility)
$authService = null;
$auditService = null;

/**
 * Get AuthService instance
 */
function getAuthService(): AuthService {
    global $authService;
    if ($authService === null) {
        $authService = new AuthService();
    }
    return $authService;
}

/**
 * Get AuditService instance
 */
function getAuditService(): AuditService {
    global $auditService;
    if ($auditService === null) {
        $auditService = new AuditService();
    }
    return $auditService;
}

/**
 * Start login process - REFACTORED
 * @param string $username
 * @param string $password
 * @return array Result array with 'ok' status and message
 */
function login_start($username, $password) {
    try {
        $result = getAuthService()->login($username, $password);
        
        // Convert new format to legacy format for backward compatibility
        return [
            'ok' => $result['success'],
            'stage' => $result['data']['stage'] ?? 'error',
            'msg' => $result['message'] ?? $result['data']['message'] ?? 'Login failed'
        ];
        
    } catch (Exception $e) {
        error_log("[AUTH] Login error: " . $e->getMessage());
        return ['ok' => false, 'msg' => 'An error occurred during login'];
    }
}

/**
 * Complete login process with OTP verification - REFACTORED
 * @param string $otp_code
 * @return array Result array with 'ok' status and message
 */
function login_complete($otp_code) {
    try {
        $result = getAuthService()->verify2FA($otp_code);
        
        // Convert new format to legacy format
        return [
            'ok' => $result['success'],
            'msg' => $result['message'] ?? ($result['success'] ? 'Login successful' : 'Verification failed')
        ];
        
    } catch (Exception $e) {
        error_log("[AUTH] 2FA verification error: " . $e->getMessage());
        return ['ok' => false, 'msg' => 'Verification failed'];
    }
}

/**
 * Get current logged-in user - REFACTORED
 * @return array|null User data or null if not logged in
 */
function current_user() {
    return getAuthService()->getCurrentUser();
}

/**
 * Require user to be logged in - REFACTORED
 * Redirects to login page if not authenticated
 */
function require_login() {
    getAuthService()->requireLogin();
}

/**
 * Complete the login process by setting session data - REFACTORED
 * @param array $user User data from database
 */
function complete_login($user) {
    // This is now handled internally by AuthService
    // Kept for backward compatibility but does nothing
}

/**
 * Logout user and destroy session - REFACTORED
 */
function logout() {
    getAuthService()->logout();
}

/**
 * Get user's primary role - REFACTORED
 * @param int $user_id
 * @return string|null Role name or null
 */
function user_primary_role($user_id) {
    $role = getAuthService()->getPrimaryRole($user_id);
    return $role ? $role['name'] : null;
}

/**
 * Check if user has a specific role - REFACTORED
 * @param int $user_id
 * @param string $role_name
 * @return bool
 */
function user_has_role($user_id, $role_name) {
    // Note: AuthService now uses userHasRole instead of hasRole to avoid conflict with BaseService
    return getAuthService()->userHasRole($role_name, $user_id);
}

/**
 * Validate CSRF token - REFACTORED
 * @param string $token Token to validate
 * @return bool
 */
function validate_csrf_token($token) {
    return getAuthService()->validateCsrfToken($token);
}

/**
 * Get CSRF token - REFACTORED
 * @return string
 */
function get_csrf_token() {
    return getAuthService()->getCsrfToken();
}

/**
 * Generate UUID v4 - REFACTORED
 * @return string
 */
function generate_uuid() {
    return UuidHelper::generate();
}

/**
 * DEPRECATED: These functions are no longer needed with the new architecture
 * but are kept for backward compatibility
 */

/**
 * Initialize session (handled by Session class now)
 */
function init_session() {
    Session::init();
}

/**
 * Check session timeout (handled by AuthService now)
 */
function check_session_timeout() {
    return Session::checkTimeout();
}

/**
 * Get user dashboard URL (handled by UserRepository now)
 */
function get_user_dashboard_url($user_id = null) {
    if ($user_id === null) {
        $user = current_user();
        if (!$user) {
            return '/dashboard';
        }
        $user_id = $user['user_id'];
    }
    
    $userRepo = new \App\Repositories\UserRepository();
    return $userRepo->getUserDashboardUrl($user_id);
}

/**
 * Log authentication event (handled by AuditService now)
 */
function log_auth_event($action, $user_id = null, $details = []) {
    getAuditService()->audit($action, 'User', $user_id, $details);
}

/**
 * Resend OTP code (new function for backward compatibility)
 */
function resend_otp() {
    $result = getAuthService()->resendOtp();
    
    return [
        'ok' => $result['success'],
        'msg' => $result['message'] ?? ($result['success'] ? 'Code resent' : 'Failed to resend code')
    ];
}

/**
 * Get all user roles (new function)
 */
function get_user_roles($user_id) {
    $userRepo = new \App\Repositories\UserRepository();
    return $userRepo->getUserRoles($user_id);
}

/**
 * Check if user is logged in (new function)
 */
function is_logged_in() {
    return getAuthService()->isLoggedIn();
}

/**
 * Get pending 2FA data (new function)
 */
function get_pending_2fa() {
    return Session::getPending2FA();
}

?>