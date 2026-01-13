/**
 * Session Management Types for SafeShift EHR
 * 
 * Type definitions for session management, active sessions,
 * and user timeout preferences.
 */

// ============================================================================
// Active Session Types
// ============================================================================

/**
 * Represents an active user session
 */
export interface ActiveSession {
  /** Unique session identifier */
  id: number;
  /** Device/browser information */
  device_info: string;
  /** IP address of the session */
  ip_address: string;
  /** When the session was created */
  created_at: string;
  /** Last activity timestamp */
  last_activity: string;
  /** Whether this is the current session */
  is_current: boolean;
}

// ============================================================================
// Session State Types
// ============================================================================

/**
 * Current session state information
 */
export interface SessionState {
  /** Whether the session is currently active */
  isActive: boolean;
  /** When the session expires */
  expiresAt: Date | null;
  /** User's configured idle timeout in seconds */
  idleTimeout: number;
  /** Whether to show the warning modal */
  showWarning: boolean;
  /** Seconds remaining until session expires */
  remainingSeconds: number;
}

// ============================================================================
// Timeout Preference Types
// ============================================================================

/**
 * User timeout preference option
 */
export interface TimeoutPreference {
  /** Timeout duration in seconds */
  idle_timeout: number;
  /** Human-readable label */
  label: string;
}

/**
 * Available timeout options for user selection
 */
export const TIMEOUT_OPTIONS: TimeoutPreference[] = [
  { idle_timeout: 300, label: '5 minutes' },
  { idle_timeout: 600, label: '10 minutes' },
  { idle_timeout: 900, label: '15 minutes' },
  { idle_timeout: 1800, label: '30 minutes' },
  { idle_timeout: 2700, label: '45 minutes' },
  { idle_timeout: 3600, label: '60 minutes' },
];

// ============================================================================
// API Response Types
// ============================================================================

/**
 * Response from ping activity endpoint
 */
export interface PingActivityResponse {
  /** Seconds remaining in current session */
  remaining_seconds: number;
  /** ISO 8601 timestamp when session expires */
  expires_at: string;
}

/**
 * Response from logout all sessions endpoint
 */
export interface LogoutAllResponse {
  /** Number of sessions logged out */
  count: number;
}

/**
 * Response from get timeout preference endpoint
 */
export interface TimeoutPreferenceResponse {
  /** User's current idle timeout in seconds */
  idle_timeout: number;
}

// ============================================================================
// Warning State Types
// ============================================================================

/**
 * State for the session warning modal
 */
export interface SessionWarningState {
  /** Whether the warning modal is visible */
  isVisible: boolean;
  /** Seconds remaining when warning was triggered */
  initialSeconds: number;
  /** Current seconds remaining (countdown) */
  currentSeconds: number;
}

// ============================================================================
// Default Values
// ============================================================================

/**
 * Default idle timeout in seconds (15 minutes)
 * HIPAA-compliant session timeout for healthcare applications
 */
export const DEFAULT_IDLE_TIMEOUT = 900;

/**
 * Warning threshold in seconds (5 minutes before expiry)
 * Warning appears at 10 minutes of inactivity (15 - 5 = 10)
 */
export const WARNING_THRESHOLD_SECONDS = 300;

/**
 * Activity ping interval in milliseconds (60 seconds)
 */
export const PING_INTERVAL_MS = 60000;

/**
 * Activity events to track for user presence
 */
export const ACTIVITY_EVENTS: string[] = [
  'mousedown',
  'keydown',
  'touchstart',
  'scroll',
  'mousemove',
  'click',
];
