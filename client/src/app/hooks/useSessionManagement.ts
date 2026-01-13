/**
 * Session Management Hook for SafeShift EHR
 * 
 * Provides session monitoring, timeout warnings, automatic
 * session refresh, and activity tracking for HIPAA-compliant session handling.
 */

import { useState, useEffect, useCallback, useRef, useMemo } from 'react';
import { useAuth } from '../contexts/AuthContext.js';
import { sessionService } from '../services/session.service.js';
import {
  ACTIVITY_EVENTS,
  DEFAULT_IDLE_TIMEOUT,
  WARNING_THRESHOLD_SECONDS,
  PING_INTERVAL_MS,
} from '../types/session.types.js';
import type { SessionState } from '../types/session.types.js';

// ============================================================================
// Configuration
// ============================================================================

/**
 * Session management configuration
 */
export interface SessionConfig {
  /** Interval in milliseconds to ping backend (default: 60 seconds) */
  pingInterval?: number;
  /** Seconds before expiration to show warning (default: 120 seconds / 2 minutes) */
  warningThreshold?: number;
  /** Enable automatic activity tracking (default: true) */
  trackActivity?: boolean;
  /** Events to track for user activity */
  activityEvents?: string[];
}

const DEFAULT_CONFIG: Required<SessionConfig> = {
  pingInterval: PING_INTERVAL_MS,
  warningThreshold: WARNING_THRESHOLD_SECONDS,
  trackActivity: true,
  activityEvents: ACTIVITY_EVENTS,
};

// ============================================================================
// Session Status Type
// ============================================================================

export interface SessionStatus {
  /** Whether the session is currently valid */
  isValid: boolean;
  /** Seconds remaining until session expires */
  remainingSeconds: number;
  /** Whether the session warning is showing */
  showWarning: boolean;
  /** Timestamp when the session will expire */
  expiresAt: Date | null;
  /** Last time the session was checked/pinged */
  lastPinged: Date | null;
  /** Whether a ping is in progress */
  isPinging: boolean;
  /** User's configured idle timeout in seconds */
  idleTimeout: number;
  /** Whether user has been active recently */
  isUserActive: boolean;
}

// ============================================================================
// Session Management Hook
// ============================================================================

/**
 * Hook for managing session lifecycle and timeout warnings
 * 
 * @param config - Session configuration options
 * @returns Session status and control functions
 * 
 * @example
 * ```typescript
 * function App() {
 *   const { 
 *     showWarning, 
 *     remainingSeconds, 
 *     extendSession, 
 *     forceLogout 
 *   } = useSessionManagement();
 *   
 *   return (
 *     <>
 *       <Routes />
 *       {showWarning && (
 *         <SessionWarningModal 
 *           seconds={remainingSeconds}
 *           onExtend={extendSession}
 *           onLogout={forceLogout}
 *         />
 *       )}
 *     </>
 *   );
 * }
 * ```
 */
export function useSessionManagement(config: SessionConfig = {}): {
  /** Current session status */
  status: SessionStatus;
  /** Whether the warning modal should be shown */
  showWarning: boolean;
  /** Seconds remaining until expiration */
  remainingSeconds: number;
  /** Minutes remaining (computed from seconds) */
  remainingMinutes: number | null;
  /** Extend the session and dismiss the warning */
  extendSession: () => Promise<void>;
  /** Force immediate logout */
  forceLogout: () => Promise<void>;
  /** Dismiss the warning without extending */
  dismissWarning: () => void;
  /** Manually trigger a session ping */
  pingSession: () => Promise<void>;
  /** Get the exact seconds remaining */
  getSessionTimeRemaining: () => number;
  /** Update user's idle timeout preference */
  setIdleTimeout: (seconds: number) => Promise<void>;
  /** Load user's idle timeout preference from backend */
  loadIdleTimeout: () => Promise<number>;
} {
  const { isAuthenticated, logout } = useAuth();
  
  // Merge config with defaults
  const mergedConfig = useMemo(() => ({ ...DEFAULT_CONFIG, ...config }), [config]);
  
  // State
  const [status, setStatus] = useState<SessionStatus>({
    isValid: true,
    remainingSeconds: DEFAULT_IDLE_TIMEOUT,
    showWarning: false,
    expiresAt: null,
    lastPinged: null,
    isPinging: false,
    idleTimeout: DEFAULT_IDLE_TIMEOUT,
    isUserActive: true,
  });
  
  // Refs for timers and activity tracking
  const pingIntervalRef = useRef<ReturnType<typeof setInterval> | null>(null);
  const countdownIntervalRef = useRef<ReturnType<typeof setInterval> | null>(null);
  const lastActivityRef = useRef<Date>(new Date());
  const isUserActiveRef = useRef<boolean>(true);
  const warningDismissedRef = useRef<boolean>(false);
  
  // ========================================================================
  // Ping Activity Function
  // ========================================================================
  
  const pingSession = useCallback(async (): Promise<void> => {
    if (!isAuthenticated || !isUserActiveRef.current) {
      return;
    }
    
    setStatus(prev => ({ ...prev, isPinging: true }));
    
    try {
      const response = await sessionService.pingActivity();
      
      const expiresAt = new Date(response.expires_at);
      const remainingSeconds = response.remaining_seconds;
      
      // Determine if we should show warning
      const shouldShowWarning = remainingSeconds <= mergedConfig.warningThreshold && 
                                remainingSeconds > 0 &&
                                !warningDismissedRef.current;
      
      setStatus(prev => ({
        ...prev,
        isValid: true,
        remainingSeconds,
        expiresAt,
        lastPinged: new Date(),
        isPinging: false,
        showWarning: shouldShowWarning,
      }));
      
    } catch (error) {
      console.error('Session ping failed:', error);
      
      // On error, check if session might be expired
      setStatus(prev => ({
        ...prev,
        isPinging: false,
        lastPinged: new Date(),
      }));
    }
  }, [isAuthenticated, mergedConfig.warningThreshold]);
  
  // ========================================================================
  // Extend Session Function
  // ========================================================================
  
  const extendSession = useCallback(async (): Promise<void> => {
    try {
      // Mark user as active
      isUserActiveRef.current = true;
      lastActivityRef.current = new Date();
      warningDismissedRef.current = false;
      
      // Ping to extend session
      await pingSession();
      
      setStatus(prev => ({
        ...prev,
        showWarning: false,
        isUserActive: true,
      }));
      
    } catch (error) {
      console.error('Failed to extend session:', error);
    }
  }, [pingSession]);
  
  // ========================================================================
  // Force Logout Function
  // ========================================================================
  
  const forceLogout = useCallback(async (): Promise<void> => {
    setStatus(prev => ({
      ...prev,
      showWarning: false,
      isValid: false,
    }));
    
    await logout();
  }, [logout]);
  
  // ========================================================================
  // Dismiss Warning Function
  // ========================================================================
  
  const dismissWarning = useCallback((): void => {
    warningDismissedRef.current = true;
    setStatus(prev => ({
      ...prev,
      showWarning: false,
    }));
  }, []);
  
  // ========================================================================
  // Get Session Time Remaining
  // ========================================================================
  
  const getSessionTimeRemaining = useCallback((): number => {
    if (!status.expiresAt) {
      return status.remainingSeconds;
    }
    
    const now = new Date();
    const remaining = Math.max(0, Math.floor((status.expiresAt.getTime() - now.getTime()) / 1000));
    return remaining;
  }, [status.expiresAt, status.remainingSeconds]);
  
  // ========================================================================
  // Set Idle Timeout Preference
  // ========================================================================
  
  const setIdleTimeout = useCallback(async (seconds: number): Promise<void> => {
    await sessionService.setTimeoutPreference(seconds);
    
    setStatus(prev => ({
      ...prev,
      idleTimeout: seconds,
    }));
  }, []);
  
  // ========================================================================
  // Load Idle Timeout Preference
  // ========================================================================
  
  const loadIdleTimeout = useCallback(async (): Promise<number> => {
    try {
      const response = await sessionService.getTimeoutPreference();
      const timeout = response.idle_timeout;
      
      setStatus(prev => ({
        ...prev,
        idleTimeout: timeout,
      }));
      
      return timeout;
    } catch (error) {
      console.error('Failed to load timeout preference:', error);
      return DEFAULT_IDLE_TIMEOUT;
    }
  }, []);
  
  // ========================================================================
  // Activity Tracking
  // ========================================================================
  
  useEffect(() => {
    if (!isAuthenticated || !mergedConfig.trackActivity) {
      return;
    }
    
    const handleActivity = (): void => {
      lastActivityRef.current = new Date();
      isUserActiveRef.current = true;
      
      // If warning was shown but dismissed, and user becomes active again,
      // reset the dismissed flag
      if (warningDismissedRef.current) {
        warningDismissedRef.current = false;
      }
      
      setStatus(prev => {
        if (!prev.isUserActive) {
          return { ...prev, isUserActive: true };
        }
        return prev;
      });
    };
    
    // Add activity listeners
    mergedConfig.activityEvents.forEach(event => {
      window.addEventListener(event, handleActivity, { passive: true });
    });
    
    return () => {
      // Cleanup listeners
      mergedConfig.activityEvents.forEach(event => {
        window.removeEventListener(event, handleActivity);
      });
    };
  }, [isAuthenticated, mergedConfig.trackActivity, mergedConfig.activityEvents]);
  
  // ========================================================================
  // Periodic Ping to Backend
  // ========================================================================
  
  useEffect(() => {
    if (!isAuthenticated) {
      // Clear interval when logged out
      if (pingIntervalRef.current) {
        clearInterval(pingIntervalRef.current);
        pingIntervalRef.current = null;
      }
      return;
    }
    
    // Initial ping
    pingSession();
    
    // Load user's timeout preference
    loadIdleTimeout();
    
    // Set up periodic pinging
    pingIntervalRef.current = setInterval(() => {
      // Only ping if user has been active
      const now = new Date();
      const timeSinceActivity = now.getTime() - lastActivityRef.current.getTime();
      
      // If user hasn't been active for more than the ping interval, mark as inactive
      if (timeSinceActivity > mergedConfig.pingInterval * 2) {
        isUserActiveRef.current = false;
        setStatus(prev => ({ ...prev, isUserActive: false }));
      }
      
      // Always ping if user is active
      if (isUserActiveRef.current) {
        pingSession();
      }
    }, mergedConfig.pingInterval);
    
    return () => {
      if (pingIntervalRef.current) {
        clearInterval(pingIntervalRef.current);
        pingIntervalRef.current = null;
      }
    };
  }, [isAuthenticated, mergedConfig.pingInterval, pingSession, loadIdleTimeout]);
  
  // ========================================================================
  // Countdown Timer (updates every second when warning is shown)
  // ========================================================================
  
  useEffect(() => {
    if (!status.showWarning || !status.expiresAt) {
      if (countdownIntervalRef.current) {
        clearInterval(countdownIntervalRef.current);
        countdownIntervalRef.current = null;
      }
      return;
    }
    
    // Update countdown every second
    countdownIntervalRef.current = setInterval(() => {
      const now = new Date();
      const remaining = Math.max(0, Math.floor((status.expiresAt!.getTime() - now.getTime()) / 1000));
      
      if (remaining <= 0) {
        // Session expired, force logout
        console.log('Session expired, logging out');
        forceLogout();
        return;
      }
      
      setStatus(prev => ({
        ...prev,
        remainingSeconds: remaining,
      }));
    }, 1000);
    
    return () => {
      if (countdownIntervalRef.current) {
        clearInterval(countdownIntervalRef.current);
        countdownIntervalRef.current = null;
      }
    };
  }, [status.showWarning, status.expiresAt, forceLogout]);
  
  // ========================================================================
  // Check for Warning Threshold
  // ========================================================================
  
  useEffect(() => {
    if (!isAuthenticated || !status.expiresAt) {
      return;
    }
    
    const checkWarning = (): void => {
      const remaining = getSessionTimeRemaining();
      
      if (remaining <= mergedConfig.warningThreshold && 
          remaining > 0 && 
          !warningDismissedRef.current &&
          !status.showWarning) {
        setStatus(prev => ({
          ...prev,
          showWarning: true,
          remainingSeconds: remaining,
        }));
      }
    };
    
    // Check immediately
    checkWarning();
    
    // Set up periodic check
    const intervalId = setInterval(checkWarning, 5000);
    
    return () => clearInterval(intervalId);
  }, [isAuthenticated, status.expiresAt, status.showWarning, mergedConfig.warningThreshold, getSessionTimeRemaining]);
  
  // ========================================================================
  // Cleanup on Unmount
  // ========================================================================
  
  useEffect(() => {
    return () => {
      if (pingIntervalRef.current) {
        clearInterval(pingIntervalRef.current);
      }
      if (countdownIntervalRef.current) {
        clearInterval(countdownIntervalRef.current);
      }
    };
  }, []);
  
  // ========================================================================
  // Computed Values
  // ========================================================================
  
  const remainingMinutes = useMemo(() => {
    if (status.remainingSeconds <= 0) return 0;
    return Math.ceil(status.remainingSeconds / 60);
  }, [status.remainingSeconds]);
  
  // ========================================================================
  // Return Values
  // ========================================================================
  
  return {
    status,
    showWarning: status.showWarning,
    remainingSeconds: status.remainingSeconds,
    remainingMinutes,
    extendSession,
    forceLogout,
    dismissWarning,
    pingSession,
    getSessionTimeRemaining,
    setIdleTimeout,
    loadIdleTimeout,
  };
}

// ============================================================================
// Session Countdown Hook
// ============================================================================

/**
 * Hook that provides a countdown timer for session expiration
 * 
 * @param expiresAt - Date when the session expires
 * @returns Countdown state with minutes and seconds
 * 
 * @example
 * ```typescript
 * function SessionWarningModal({ expiresAt }) {
 *   const { minutes, seconds, formatted } = useSessionCountdown(expiresAt);
 *   
 *   return (
 *     <div>
 *       <p>Session expires in: {formatted}</p>
 *     </div>
 *   );
 * }
 * ```
 */
export function useSessionCountdown(expiresAt: Date | null): {
  /** Minutes remaining */
  minutes: number;
  /** Seconds remaining */
  seconds: number;
  /** Formatted string (MM:SS) */
  formatted: string;
  /** Total seconds remaining */
  totalSeconds: number;
} {
  const [countdown, setCountdown] = useState({
    minutes: 0,
    seconds: 0,
    formatted: '00:00',
    totalSeconds: 0,
  });
  
  useEffect(() => {
    if (!expiresAt) {
      return;
    }
    
    const updateCountdown = (): void => {
      const now = new Date();
      const remaining = Math.max(0, expiresAt.getTime() - now.getTime());
      const totalSeconds = Math.floor(remaining / 1000);
      const minutes = Math.floor(totalSeconds / 60);
      const seconds = totalSeconds % 60;
      
      const formatted = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
      
      setCountdown({ minutes, seconds, formatted, totalSeconds });
    };
    
    // Initial update
    updateCountdown();
    
    // Update every second
    const intervalId = setInterval(updateCountdown, 1000);
    
    return () => clearInterval(intervalId);
  }, [expiresAt]);
  
  return countdown;
}

// ============================================================================
// Last Activity Hook
// ============================================================================

/**
 * Hook to track and display last user activity time
 * 
 * @returns Last activity timestamp and human-readable string
 */
export function useLastActivity(): {
  lastActivity: Date;
  lastActivityString: string;
} {
  const [lastActivity, setLastActivity] = useState(new Date());
  const [lastActivityString, setLastActivityString] = useState('Just now');
  
  useEffect(() => {
    const handleActivity = (): void => {
      setLastActivity(new Date());
      setLastActivityString('Just now');
    };
    
    const events = ['mousedown', 'keydown', 'touchstart'];
    events.forEach(event => {
      window.addEventListener(event, handleActivity, { passive: true });
    });
    
    // Update the string periodically
    const intervalId = setInterval(() => {
      const now = new Date();
      const diffMs = now.getTime() - lastActivity.getTime();
      const diffMinutes = Math.floor(diffMs / (1000 * 60));
      
      if (diffMinutes < 1) {
        setLastActivityString('Just now');
      } else if (diffMinutes === 1) {
        setLastActivityString('1 minute ago');
      } else if (diffMinutes < 60) {
        setLastActivityString(`${diffMinutes} minutes ago`);
      } else {
        const diffHours = Math.floor(diffMinutes / 60);
        setLastActivityString(`${diffHours} hour${diffHours > 1 ? 's' : ''} ago`);
      }
    }, 30000); // Update every 30 seconds
    
    return () => {
      events.forEach(event => {
        window.removeEventListener(event, handleActivity);
      });
      clearInterval(intervalId);
    };
  }, [lastActivity]);
  
  return { lastActivity, lastActivityString };
}

// ============================================================================
// Format Time Utility
// ============================================================================

/**
 * Format seconds into a human-readable string
 * 
 * @param seconds - Total seconds
 * @returns Formatted string (e.g., "2 minutes 30 seconds")
 */
export function formatTimeRemaining(seconds: number): string {
  if (seconds <= 0) return '0 seconds';
  
  const minutes = Math.floor(seconds / 60);
  const remainingSeconds = seconds % 60;
  
  if (minutes === 0) {
    return `${remainingSeconds} second${remainingSeconds !== 1 ? 's' : ''}`;
  }
  
  if (remainingSeconds === 0) {
    return `${minutes} minute${minutes !== 1 ? 's' : ''}`;
  }
  
  return `${minutes} minute${minutes !== 1 ? 's' : ''} ${remainingSeconds} second${remainingSeconds !== 1 ? 's' : ''}`;
}

// ============================================================================
// Export Default
// ============================================================================

export default useSessionManagement;
