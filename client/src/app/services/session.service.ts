/**
 * Session Management Service for SafeShift EHR
 * 
 * Handles all session-related API calls including activity pinging,
 * active session management, and timeout preference handling.
 */

import { post, get, put } from './api.js';
import type {
  ActiveSession,
  PingActivityResponse,
  LogoutAllResponse,
  TimeoutPreferenceResponse,
} from '../types/session.types.js';

// ============================================================================
// Session Management Endpoints
// ============================================================================

const SESSION_ENDPOINTS = {
  pingActivity: '/auth/ping-activity',
  activeSessions: '/auth/active-sessions',
  logoutSession: '/auth/logout-session',
  logoutAll: '/auth/logout-all',
  logoutEverywhere: '/auth/logout-everywhere',
  timeoutPreference: '/user/preferences/timeout',
} as const;

// ============================================================================
// Session Activity Functions
// ============================================================================

/**
 * Ping the backend to update session activity
 * 
 * This should be called periodically (every 60 seconds) when the user
 * is actively using the application to keep the session alive.
 * 
 * @returns Promise resolving to remaining session time and expiry
 * 
 * @example
 * ```typescript
 * const { remaining_seconds, expires_at } = await pingActivity();
 * console.log(`Session expires in ${remaining_seconds} seconds`);
 * ```
 */
export async function pingActivity(): Promise<PingActivityResponse> {
  const response = await post<PingActivityResponse>(SESSION_ENDPOINTS.pingActivity);
  return response.data;
}

// ============================================================================
// Active Sessions Management Functions
// ============================================================================

/**
 * Get list of all active sessions for the current user
 * 
 * @returns Promise resolving to array of active sessions
 * 
 * @example
 * ```typescript
 * const sessions = await getActiveSessions();
 * const currentSession = sessions.find(s => s.is_current);
 * const otherSessions = sessions.filter(s => !s.is_current);
 * ```
 */
export async function getActiveSessions(): Promise<ActiveSession[]> {
  const response = await get<ActiveSession[]>(SESSION_ENDPOINTS.activeSessions);
  return response.data;
}

/**
 * Log out a specific session by its ID
 * 
 * Used to remotely terminate sessions on other devices.
 * Cannot be used to log out the current session.
 * 
 * @param sessionId - The ID of the session to terminate
 * @returns Promise that resolves when session is logged out
 * @throws ApiError if session doesn't exist or is the current session
 * 
 * @example
 * ```typescript
 * await logoutSession(123);
 * console.log('Session terminated');
 * ```
 */
export async function logoutSession(sessionId: number): Promise<void> {
  await post<void, { session_id: number }>(SESSION_ENDPOINTS.logoutSession, {
    session_id: sessionId,
  });
}

/**
 * Log out all sessions except the current one
 * 
 * Useful for security purposes when user suspects unauthorized access.
 * 
 * @returns Promise resolving to count of sessions logged out
 * 
 * @example
 * ```typescript
 * const { count } = await logoutAllOtherSessions();
 * console.log(`Logged out ${count} other sessions`);
 * ```
 */
export async function logoutAllOtherSessions(): Promise<LogoutAllResponse> {
  const response = await post<LogoutAllResponse>(SESSION_ENDPOINTS.logoutAll);
  return response.data;
}

/**
 * Log out all sessions including the current one
 * 
 * This will force the user to re-authenticate on all devices.
 * Should be followed by a redirect to the login page.
 * 
 * @returns Promise that resolves when all sessions are logged out
 * 
 * @example
 * ```typescript
 * await logoutEverywhere();
 * navigate('/login');
 * ```
 */
export async function logoutEverywhere(): Promise<void> {
  await post<void>(SESSION_ENDPOINTS.logoutEverywhere);
}

// ============================================================================
// Timeout Preference Functions
// ============================================================================

/**
 * Get the user's current idle timeout preference
 * 
 * @returns Promise resolving to the timeout preference in seconds
 * 
 * @example
 * ```typescript
 * const { idle_timeout } = await getTimeoutPreference();
 * console.log(`User's timeout is ${idle_timeout / 60} minutes`);
 * ```
 */
export async function getTimeoutPreference(): Promise<TimeoutPreferenceResponse> {
  const response = await get<TimeoutPreferenceResponse>(SESSION_ENDPOINTS.timeoutPreference);
  return response.data;
}

/**
 * Update the user's idle timeout preference
 * 
 * @param seconds - The new timeout value in seconds
 * @returns Promise that resolves when preference is updated
 * @throws ApiError if value is invalid (must be 300-3600)
 * 
 * @example
 * ```typescript
 * // Set timeout to 30 minutes
 * await setTimeoutPreference(1800);
 * ```
 */
export async function setTimeoutPreference(seconds: number): Promise<void> {
  await put<void, { idle_timeout: number }>(SESSION_ENDPOINTS.timeoutPreference, {
    idle_timeout: seconds,
  });
}

// ============================================================================
// Export as Namespace Object
// ============================================================================

/**
 * Session service object containing all session-related methods
 */
export const sessionService = {
  // Activity
  pingActivity,
  
  // Sessions management
  getActiveSessions,
  logoutSession,
  logoutAllOtherSessions,
  logoutEverywhere,
  
  // Timeout preferences
  getTimeoutPreference,
  setTimeoutPreference,
} as const;

export default sessionService;
