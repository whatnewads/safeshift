<?php
/**
 * Global Authentication Functions for Backward Compatibility
 * 
 * These functions are defined in the global namespace to maintain
 * backward compatibility with legacy code
 */

// Ensure auth.php functions are available
require_once __DIR__ . '/auth.php';

/**
 * Check if user is authenticated (legacy function)
 *
 * @deprecated Use \Core\Services\AuthService::isLoggedIn() instead
 * @return bool
 */
function is_authenticated() {
    return \App\auth\getAuthService()->isLoggedIn();
}

/**
 * Check if user is authenticated (camelCase version)
 *
 * @deprecated Use \Core\Services\AuthService::isLoggedIn() instead
 * @return bool
 */
function isAuthenticated() {
    return \App\auth\getAuthService()->isLoggedIn();
}

/**
 * Check if current user has a specific role
 *
 * @deprecated Use \Core\Services\AuthService::userHasRole() instead
 * @param string $role Role name to check
 * @return bool
 */
function hasRole($role) {
    return \App\auth\getAuthService()->userHasRole($role);
}

/**
 * Redirect to a new location
 *
 * @deprecated Use header("Location: $location") directly in your code
 * @param string $location URL to redirect to
 * @return void
 */
function redirect_to($location) {
    header("Location: $location");
    exit();
}