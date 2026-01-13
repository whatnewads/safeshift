/**
 * Authentication Service for SafeShift EHR
 * 
 * Handles all authentication-related API calls including login,
 * 2FA verification, session management, and CSRF token handling.
 */

import { post, get, setCsrfToken, clearCsrfToken } from './api.js';
import type {
  LoginRequest,
  LoginResponse,
  AuthResponse,
  SessionResponse,
  Verify2FARequest,
} from '../types/api.types.js';
import type { User } from '../types/index.js';

// ============================================================================
// Authentication Endpoints
// ============================================================================

const AUTH_ENDPOINTS = {
  login: '/auth/login',
  logout: '/auth/logout',
  verify2FA: '/auth/verify-2fa',
  resendOtp: '/auth/resend-otp',
  session: '/auth/session-status',
  csrf: '/auth/csrf-token',
  refresh: '/auth/refresh-session',
  me: '/auth/current-user',
} as const;

// ============================================================================
// Authentication Service
// ============================================================================

/**
 * Authenticate a user with username and password
 * 
 * @param username - The user's username or email
 * @param password - The user's password
 * @returns Promise resolving to login response (may require 2FA)
 * 
 * @example
 * ```typescript
 * const response = await login('user@example.com', 'password123');
 * if (response.stage === 'otp') {
 *   // Redirect to 2FA verification
 * } else {
 *   // User is fully authenticated
 * }
 * ```
 */
export async function login(
  username: string,
  password: string
): Promise<LoginResponse> {
  console.log('[auth.service] login() called with username:', username);
  
  try {
    // api.post() returns ApiResponse<LoginResponse> = {success, message, data: LoginResponse}
    // where LoginResponse = {stage, user, csrfToken, otpMethod, otpDestination}
    const response = await post<LoginResponse, LoginRequest>(AUTH_ENDPOINTS.login, {
      username,
      password,
    });
    
    console.log('[auth.service] login() raw response:', response);
    console.log('[auth.service] login() response.success:', response.success);
    console.log('[auth.service] login() response.data:', response.data);
    
    // Extract the actual login response data from the API wrapper
    // response is {success, message, data: {stage, user, ...}}
    // We need the inner .data which contains {stage, user, csrfToken, ...}
    const loginData: LoginResponse = response.data;
    
    console.log('[auth.service] login() loginData:', loginData);
    console.log('[auth.service] login() loginData.stage:', loginData?.stage);
    
    // Validate that we got proper data
    if (!loginData || typeof loginData !== 'object') {
      console.error('[auth.service] login() Invalid response data:', loginData);
      throw new Error('Invalid login response from server');
    }
    
    // If login is complete, store the CSRF token
    if (loginData.stage === 'complete' && loginData.csrfToken) {
      console.log('[auth.service] login() complete - storing CSRF token');
      setCsrfToken(loginData.csrfToken);
    } else if (loginData.stage === 'otp') {
      console.log('[auth.service] login() OTP stage - 2FA required');
    }
    
    // Return the LoginResponse object (not the full ApiResponse wrapper)
    return loginData;
  } catch (err) {
    console.error('[auth.service] login() CAUGHT ERROR:', err);
    console.error('[auth.service] login() error type:', typeof err);
    console.error('[auth.service] login() error name:', (err as Error)?.name);
    console.error('[auth.service] login() error message:', (err as Error)?.message);
    if (err && typeof err === 'object' && 'response' in err) {
      const axiosErr = err as { response?: { status?: number; data?: unknown; headers?: unknown } };
      console.error('[auth.service] login() axios response status:', axiosErr.response?.status);
      console.error('[auth.service] login() axios response data:', axiosErr.response?.data);
      console.error('[auth.service] login() axios response headers:', axiosErr.response?.headers);
    }
    throw err;
  }
}

/**
 * Verify the 2FA code submitted by the user
 * 
 * @param code - The 6-digit OTP code
 * @param trustDevice - Whether to remember this device for future logins
 * @returns Promise resolving to full authentication response with user data
 * 
 * @example
 * ```typescript
 * const auth = await verify2FA('123456', true);
 * console.log('Logged in as:', auth.user.name);
 * ```
 */
export async function verify2FA(
  code: string,
  trustDevice = false
): Promise<AuthResponse> {
  console.log('[auth.service] verify2FA called');
  console.log('[auth.service] Endpoint:', AUTH_ENDPOINTS.verify2FA);
  console.log('[auth.service] Payload:', { code: '***' + code.slice(-3), trustDevice });
  
  const response = await post<AuthResponse, Verify2FARequest>(AUTH_ENDPOINTS.verify2FA, {
    code,
    trustDevice,
  });
  
  console.log('[auth.service] verify2FA response:', response);
  
  // Store the CSRF token from the response
  if (response.data.csrfToken) {
    setCsrfToken(response.data.csrfToken);
  }
  
  return response.data;
}

/**
 * Resend the OTP code to the user
 * 
 * @returns Promise that resolves when OTP is successfully resent
 * @throws ApiError if rate limited or session expired
 * 
 * @example
 * ```typescript
 * try {
 *   await resendOtp();
 *   showNotification('A new code has been sent to your email');
 * } catch (error) {
 *   showError('Please wait before requesting another code');
 * }
 * ```
 */
export async function resendOtp(): Promise<void> {
  await post<void>(AUTH_ENDPOINTS.resendOtp);
}

/**
 * Log out the current user and destroy the session
 * 
 * @returns Promise that resolves when logout is complete
 * 
 * @example
 * ```typescript
 * await logout();
 * // Clear local state and redirect to login
 * navigate('/login');
 * ```
 */
export async function logout(): Promise<void> {
  try {
    await post<void>(AUTH_ENDPOINTS.logout);
  } finally {
    // Always clear the CSRF token, even if the request fails
    clearCsrfToken();
  }
}

/**
 * Get the currently authenticated user's information
 * 
 * @returns Promise resolving to the current user or null if not authenticated
 * 
 * @example
 * ```typescript
 * const user = await getCurrentUser();
 * if (user) {
 *   console.log('Current user:', user.name);
 * } else {
 *   // Not logged in
 * }
 * ```
 */
export async function getCurrentUser(): Promise<User | null> {
  try {
    const response = await get<User>(AUTH_ENDPOINTS.me);
    return response.data;
  } catch {
    // If the request fails (401, etc.), return null
    return null;
  }
}

/**
 * Get a fresh CSRF token from the server
 * 
 * This is useful after session changes or when the token expires.
 * The token is automatically stored for subsequent requests.
 * 
 * @returns Promise resolving to the CSRF token string
 * 
 * @example
 * ```typescript
 * const token = await getCsrfToken();
 * // Token is now stored and will be used for subsequent requests
 * ```
 */
export async function getCsrfToken(): Promise<string> {
  const response = await get<{ csrfToken: string }>(AUTH_ENDPOINTS.csrf);
  const token = response.data.csrfToken;
  setCsrfToken(token);
  return token;
}

/**
 * Refresh the current session to extend its lifetime
 * 
 * This should be called periodically for active users to prevent
 * session timeout during long operations.
 * 
 * @returns Promise that resolves when session is refreshed
 * @throws ApiError if session is invalid or expired
 * 
 * @example
 * ```typescript
 * // Set up periodic session refresh
 * setInterval(async () => {
 *   try {
 *     await refreshSession();
 *   } catch (error) {
 *     // Session expired, redirect to login
 *     navigate('/login');
 *   }
 * }, 5 * 60 * 1000); // Every 5 minutes
 * ```
 */
export async function refreshSession(): Promise<void> {
  const response = await post<{ csrfToken?: string }>(AUTH_ENDPOINTS.refresh);
  
  // Update CSRF token if a new one is provided
  if (response.data.csrfToken) {
    setCsrfToken(response.data.csrfToken);
  }
}

/**
 * Check if the current session is valid
 * 
 * @returns Promise resolving to session status information
 * 
 * @example
 * ```typescript
 * const session = await getSessionStatus();
 * if (session.valid) {
 *   console.log('Session valid until:', session.expiresAt);
 * } else {
 *   // Redirect to login
 * }
 * ```
 */
export async function getSessionStatus(): Promise<SessionResponse> {
  try {
    const response = await get<SessionResponse>(AUTH_ENDPOINTS.session);
    
    // Update CSRF token if provided
    if (response.data.csrfToken) {
      setCsrfToken(response.data.csrfToken);
    }
    
    return response.data;
  } catch {
    // If request fails, session is invalid
    return { valid: false };
  }
}

// ============================================================================
// Export as Namespace Object
// ============================================================================

/**
 * Authentication service object containing all auth-related methods
 */
export const authService = {
  login,
  verify2FA,
  resendOtp,
  logout,
  getCurrentUser,
  getCsrfToken,
  refreshSession,
  getSessionStatus,
} as const;

export default authService;
