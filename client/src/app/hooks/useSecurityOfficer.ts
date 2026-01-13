/**
 * Security Officer Dashboard Hook
 *
 * React hooks for security officer dashboard functionality including security statistics,
 * audit events, failed login attempts, MFA status, active sessions, security alerts,
 * and user devices.
 *
 * Part of the Security Officer Dashboard workflow: View → Hook → Service → API
 *
 * @module hooks/useSecurityOfficer
 */

import { useState, useEffect, useCallback } from 'react';
import {
  getDashboardData,
  getSecurityStats,
  getAuditEvents,
  getFailedLoginAttempts,
  getMFAStatus,
  getActiveSessions,
  getSecurityAlerts,
  getUserDevices,
} from '../services/security.service.js';
import type {
  SecurityStats,
  AuditEvent,
  FailedLogin,
  MFAStatus,
  MFAUser,
  ActiveSession,
  SecurityAlert,
  UserDevice,
  SecurityOfficerDashboardData,
} from '../services/security.service.js';

// ============================================================================
// Types
// ============================================================================

/**
 * Return type for the main security officer dashboard hook
 */
interface UseSecurityOfficerReturn {
  /** Security statistics (failed logins, active sessions, MFA compliance, anomalies) */
  stats: SecurityStats | null;
  /** List of audit events */
  auditEvents: AuditEvent[];
  /** List of failed login attempts */
  failedLogins: FailedLogin[];
  /** MFA status overview */
  mfaStatus: MFAStatus | null;
  /** List of active sessions */
  activeSessions: ActiveSession[];
  /** List of security alerts */
  securityAlerts: SecurityAlert[];
  /** Loading state */
  loading: boolean;
  /** Error message if any */
  error: string | null;
  /** Refetch dashboard data */
  refetch: () => Promise<void>;
  /** Clear any errors */
  clearError: () => void;
}

/**
 * Return type for the security stats hook
 */
interface UseSecurityStatsReturn {
  /** Security statistics */
  stats: SecurityStats | null;
  /** Loading state */
  loading: boolean;
  /** Error message if any */
  error: string | null;
  /** Refetch stats */
  refetch: () => Promise<void>;
}

/**
 * Return type for the audit events hook
 */
interface UseAuditEventsReturn {
  /** List of audit events */
  auditEvents: AuditEvent[];
  /** Loading state */
  loading: boolean;
  /** Error message if any */
  error: string | null;
  /** Refetch audit events */
  refetch: () => Promise<void>;
  /** Set the limit for results */
  setLimit: (limit: number) => void;
}

/**
 * Return type for the failed login attempts hook
 */
interface UseFailedLoginAttemptsReturn {
  /** List of failed login attempts */
  failedLogins: FailedLogin[];
  /** Loading state */
  loading: boolean;
  /** Error message if any */
  error: string | null;
  /** Refetch failed logins */
  refetch: () => Promise<void>;
  /** Set the limit for results */
  setLimit: (limit: number) => void;
}

/**
 * Return type for the MFA status hook
 */
interface UseMFAStatusReturn {
  /** MFA status overview */
  mfaStatus: MFAStatus | null;
  /** Loading state */
  loading: boolean;
  /** Error message if any */
  error: string | null;
  /** Refetch MFA status */
  refetch: () => Promise<void>;
}

/**
 * Return type for the active sessions hook
 */
interface UseActiveSessionsReturn {
  /** List of active sessions */
  activeSessions: ActiveSession[];
  /** Loading state */
  loading: boolean;
  /** Error message if any */
  error: string | null;
  /** Refetch active sessions */
  refetch: () => Promise<void>;
  /** Set the limit for results */
  setLimit: (limit: number) => void;
}

/**
 * Return type for the security alerts hook
 */
interface UseSecurityAlertsReturn {
  /** List of security alerts */
  securityAlerts: SecurityAlert[];
  /** Loading state */
  loading: boolean;
  /** Error message if any */
  error: string | null;
  /** Refetch security alerts */
  refetch: () => Promise<void>;
  /** Set the limit for results */
  setLimit: (limit: number) => void;
}

/**
 * Return type for the user devices hook
 */
interface UseUserDevicesReturn {
  /** List of user devices */
  userDevices: UserDevice[];
  /** Loading state */
  loading: boolean;
  /** Error message if any */
  error: string | null;
  /** Refetch user devices */
  refetch: () => Promise<void>;
  /** Set the limit for results */
  setLimit: (limit: number) => void;
}

// ============================================================================
// useSecurityOfficer Hook (Main Dashboard Hook)
// ============================================================================

/**
 * Main hook for security officer dashboard data
 * 
 * Fetches complete dashboard data on mount and provides refetch capability.
 * 
 * @returns Security officer dashboard state and operations
 * 
 * @example
 * ```typescript
 * function SecurityOfficerDashboard() {
 *   const { stats, auditEvents, failedLogins, mfaStatus, loading, error, refetch } = useSecurityOfficer();
 *   
 *   if (loading) return <LoadingSpinner />;
 *   if (error) return <ErrorMessage message={error} onRetry={refetch} />;
 *   
 *   return (
 *     <div>
 *       <SecurityStatsDisplay stats={stats} />
 *       <AuditEventsList events={auditEvents} />
 *       <button onClick={refetch}>Refresh</button>
 *     </div>
 *   );
 * }
 * ```
 */
export function useSecurityOfficer(): UseSecurityOfficerReturn {
  const [stats, setStats] = useState<SecurityStats | null>(null);
  const [auditEvents, setAuditEvents] = useState<AuditEvent[]>([]);
  const [failedLogins, setFailedLogins] = useState<FailedLogin[]>([]);
  const [mfaStatus, setMfaStatus] = useState<MFAStatus | null>(null);
  const [activeSessions, setActiveSessions] = useState<ActiveSession[]>([]);
  const [securityAlerts, setSecurityAlerts] = useState<SecurityAlert[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const fetchDashboard = useCallback(async () => {
    try {
      setLoading(true);
      setError(null);
      const data = await getDashboardData();
      setStats(data.stats);
      setAuditEvents(data.auditEvents);
      setFailedLogins(data.failedLogins);
      setMfaStatus(data.mfaStatus);
      setActiveSessions(data.activeSessions);
      setSecurityAlerts(data.securityAlerts);
    } catch (err) {
      const message = err instanceof Error ? err.message : 'Failed to load security officer dashboard';
      setError(message);
    } finally {
      setLoading(false);
    }
  }, []);

  const clearError = useCallback(() => {
    setError(null);
  }, []);

  useEffect(() => {
    fetchDashboard();
  }, [fetchDashboard]);

  return {
    stats,
    auditEvents,
    failedLogins,
    mfaStatus,
    activeSessions,
    securityAlerts,
    loading,
    error,
    refetch: fetchDashboard,
    clearError,
  };
}

// ============================================================================
// useSecurityStats Hook
// ============================================================================

/**
 * Hook for security statistics only
 * 
 * Useful for displaying just the security metrics without the full data lists.
 * 
 * @returns Security stats state and operations
 * 
 * @example
 * ```typescript
 * function SecurityStatusBar() {
 *   const { stats, loading } = useSecurityStats();
 *   
 *   if (!stats) return null;
 *   
 *   return (
 *     <div>
 *       Failed Logins: {stats.failedLogins24h} | 
 *       Active Sessions: {stats.activeSessions} | 
 *       MFA: {stats.mfaCompliance}%
 *     </div>
 *   );
 * }
 * ```
 */
export function useSecurityStats(): UseSecurityStatsReturn {
  const [stats, setStats] = useState<SecurityStats | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const fetchStats = useCallback(async () => {
    try {
      setLoading(true);
      setError(null);
      const securityStats = await getSecurityStats();
      setStats(securityStats);
    } catch (err) {
      const message = err instanceof Error ? err.message : 'Failed to load security statistics';
      setError(message);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    fetchStats();
  }, [fetchStats]);

  return {
    stats,
    loading,
    error,
    refetch: fetchStats,
  };
}

// ============================================================================
// useAuditEvents Hook
// ============================================================================

/**
 * Hook for audit events
 * 
 * Fetches recent security-related audit events.
 * 
 * @param initialLimit - Initial limit for results (default: 20)
 * @returns Audit events state and operations
 * 
 * @example
 * ```typescript
 * function AuditEventsTable() {
 *   const { auditEvents, loading, setLimit } = useAuditEvents(50);
 *   
 *   return (
 *     <div>
 *       {auditEvents.map(event => (
 *         <AuditEventRow key={event.id} event={event} />
 *       ))}
 *       <button onClick={() => setLimit(100)}>Show More</button>
 *     </div>
 *   );
 * }
 * ```
 */
export function useAuditEvents(initialLimit = 20): UseAuditEventsReturn {
  const [auditEvents, setAuditEvents] = useState<AuditEvent[]>([]);
  const [limit, setLimitState] = useState(initialLimit);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const fetchEvents = useCallback(async () => {
    try {
      setLoading(true);
      setError(null);
      const events = await getAuditEvents(limit);
      setAuditEvents(events);
    } catch (err) {
      const message = err instanceof Error ? err.message : 'Failed to load audit events';
      setError(message);
    } finally {
      setLoading(false);
    }
  }, [limit]);

  useEffect(() => {
    fetchEvents();
  }, [fetchEvents]);

  const setLimit = useCallback((newLimit: number) => {
    setLimitState(Math.min(Math.max(1, newLimit), 100));
  }, []);

  return {
    auditEvents,
    loading,
    error,
    refetch: fetchEvents,
    setLimit,
  };
}

// ============================================================================
// useFailedLoginAttempts Hook
// ============================================================================

/**
 * Hook for failed login attempts
 * 
 * Fetches recent failed login attempts for security monitoring.
 * 
 * @param initialLimit - Initial limit for results (default: 20)
 * @returns Failed login attempts state and operations
 * 
 * @example
 * ```typescript
 * function FailedLoginsTable() {
 *   const { failedLogins, loading } = useFailedLoginAttempts(20);
 *   
 *   return (
 *     <div>
 *       {failedLogins.map(login => (
 *         <FailedLoginRow key={login.id} login={login} />
 *       ))}
 *     </div>
 *   );
 * }
 * ```
 */
export function useFailedLoginAttempts(initialLimit = 20): UseFailedLoginAttemptsReturn {
  const [failedLogins, setFailedLogins] = useState<FailedLogin[]>([]);
  const [limit, setLimitState] = useState(initialLimit);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const fetchLogins = useCallback(async () => {
    try {
      setLoading(true);
      setError(null);
      const logins = await getFailedLoginAttempts(limit);
      setFailedLogins(logins);
    } catch (err) {
      const message = err instanceof Error ? err.message : 'Failed to load failed login attempts';
      setError(message);
    } finally {
      setLoading(false);
    }
  }, [limit]);

  useEffect(() => {
    fetchLogins();
  }, [fetchLogins]);

  const setLimit = useCallback((newLimit: number) => {
    setLimitState(Math.min(Math.max(1, newLimit), 100));
  }, []);

  return {
    failedLogins,
    loading,
    error,
    refetch: fetchLogins,
    setLimit,
  };
}

// ============================================================================
// useMFAStatus Hook
// ============================================================================

/**
 * Hook for MFA status
 * 
 * Fetches MFA enrollment and compliance status across all users.
 * 
 * @returns MFA status state and operations
 * 
 * @example
 * ```typescript
 * function MFACompliancePanel() {
 *   const { mfaStatus, loading } = useMFAStatus();
 *   
 *   if (!mfaStatus) return null;
 *   
 *   return (
 *     <div>
 *       <p>MFA Compliance: {mfaStatus.complianceRate}%</p>
 *       <p>Enabled: {mfaStatus.enabled}</p>
 *       <p>Disabled: {mfaStatus.disabled}</p>
 *       <p>Pending: {mfaStatus.pending}</p>
 *     </div>
 *   );
 * }
 * ```
 */
export function useMFAStatus(): UseMFAStatusReturn {
  const [mfaStatus, setMfaStatus] = useState<MFAStatus | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const fetchStatus = useCallback(async () => {
    try {
      setLoading(true);
      setError(null);
      const status = await getMFAStatus();
      setMfaStatus(status);
    } catch (err) {
      const message = err instanceof Error ? err.message : 'Failed to load MFA status';
      setError(message);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    fetchStatus();
  }, [fetchStatus]);

  return {
    mfaStatus,
    loading,
    error,
    refetch: fetchStatus,
  };
}

// ============================================================================
// useActiveSessions Hook
// ============================================================================

/**
 * Hook for active sessions
 * 
 * Fetches currently active user sessions.
 * 
 * @param initialLimit - Initial limit for results (default: 20)
 * @returns Active sessions state and operations
 * 
 * @example
 * ```typescript
 * function ActiveSessionsTable() {
 *   const { activeSessions, loading, refetch } = useActiveSessions(20);
 *   
 *   return (
 *     <div>
 *       <h3>Active Sessions: {activeSessions.length}</h3>
 *       {activeSessions.map(session => (
 *         <SessionRow key={session.id} session={session} />
 *       ))}
 *       <button onClick={refetch}>Refresh</button>
 *     </div>
 *   );
 * }
 * ```
 */
export function useActiveSessions(initialLimit = 20): UseActiveSessionsReturn {
  const [activeSessions, setActiveSessions] = useState<ActiveSession[]>([]);
  const [limit, setLimitState] = useState(initialLimit);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const fetchSessions = useCallback(async () => {
    try {
      setLoading(true);
      setError(null);
      const sessions = await getActiveSessions(limit);
      setActiveSessions(sessions);
    } catch (err) {
      const message = err instanceof Error ? err.message : 'Failed to load active sessions';
      setError(message);
    } finally {
      setLoading(false);
    }
  }, [limit]);

  useEffect(() => {
    fetchSessions();
  }, [fetchSessions]);

  const setLimit = useCallback((newLimit: number) => {
    setLimitState(Math.min(Math.max(1, newLimit), 100));
  }, []);

  return {
    activeSessions,
    loading,
    error,
    refetch: fetchSessions,
    setLimit,
  };
}

// ============================================================================
// useSecurityAlerts Hook
// ============================================================================

/**
 * Hook for security alerts
 * 
 * Fetches security alerts and anomalies.
 * 
 * @param initialLimit - Initial limit for results (default: 20)
 * @returns Security alerts state and operations
 * 
 * @example
 * ```typescript
 * function SecurityAlertsPanel() {
 *   const { securityAlerts, loading } = useSecurityAlerts(20);
 *   
 *   const critical = securityAlerts.filter(a => a.severity === 'critical');
 *   
 *   return (
 *     <div>
 *       <h3>Critical Alerts: {critical.length}</h3>
 *       {securityAlerts.map(alert => (
 *         <AlertCard key={alert.id} alert={alert} />
 *       ))}
 *     </div>
 *   );
 * }
 * ```
 */
export function useSecurityAlerts(initialLimit = 20): UseSecurityAlertsReturn {
  const [securityAlerts, setSecurityAlerts] = useState<SecurityAlert[]>([]);
  const [limit, setLimitState] = useState(initialLimit);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const fetchAlerts = useCallback(async () => {
    try {
      setLoading(true);
      setError(null);
      const alerts = await getSecurityAlerts(limit);
      setSecurityAlerts(alerts);
    } catch (err) {
      const message = err instanceof Error ? err.message : 'Failed to load security alerts';
      setError(message);
    } finally {
      setLoading(false);
    }
  }, [limit]);

  useEffect(() => {
    fetchAlerts();
  }, [fetchAlerts]);

  const setLimit = useCallback((newLimit: number) => {
    setLimitState(Math.min(Math.max(1, newLimit), 100));
  }, []);

  return {
    securityAlerts,
    loading,
    error,
    refetch: fetchAlerts,
    setLimit,
  };
}

// ============================================================================
// useUserDevices Hook
// ============================================================================

/**
 * Hook for user devices
 * 
 * Fetches registered user devices with security status.
 * 
 * @param initialLimit - Initial limit for results (default: 20)
 * @returns User devices state and operations
 * 
 * @example
 * ```typescript
 * function UserDevicesTable() {
 *   const { userDevices, loading } = useUserDevices(20);
 *   
 *   const unencrypted = userDevices.filter(d => !d.encryptedAtRest);
 *   
 *   return (
 *     <div>
 *       <h3>Unencrypted Devices: {unencrypted.length}</h3>
 *       {userDevices.map(device => (
 *         <DeviceRow key={device.id} device={device} />
 *       ))}
 *     </div>
 *   );
 * }
 * ```
 */
export function useUserDevices(initialLimit = 20): UseUserDevicesReturn {
  const [userDevices, setUserDevices] = useState<UserDevice[]>([]);
  const [limit, setLimitState] = useState(initialLimit);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const fetchDevices = useCallback(async () => {
    try {
      setLoading(true);
      setError(null);
      const devices = await getUserDevices(limit);
      setUserDevices(devices);
    } catch (err) {
      const message = err instanceof Error ? err.message : 'Failed to load user devices';
      setError(message);
    } finally {
      setLoading(false);
    }
  }, [limit]);

  useEffect(() => {
    fetchDevices();
  }, [fetchDevices]);

  const setLimit = useCallback((newLimit: number) => {
    setLimitState(Math.min(Math.max(1, newLimit), 100));
  }, []);

  return {
    userDevices,
    loading,
    error,
    refetch: fetchDevices,
    setLimit,
  };
}

// ============================================================================
// Re-export types for convenience
// ============================================================================

export type {
  SecurityStats,
  AuditEvent,
  FailedLogin,
  MFAStatus,
  MFAUser,
  ActiveSession,
  SecurityAlert,
  UserDevice,
  SecurityOfficerDashboardData,
} from '../services/security.service.js';

// ============================================================================
// Export Default
// ============================================================================

export default {
  useSecurityOfficer,
  useSecurityStats,
  useAuditEvents,
  useFailedLoginAttempts,
  useMFAStatus,
  useActiveSessions,
  useSecurityAlerts,
  useUserDevices,
};
