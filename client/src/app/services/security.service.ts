/**
 * Security Officer Service
 *
 * Handles API calls for security officer dashboard including security statistics,
 * audit events, failed login attempts, MFA status, active sessions, security alerts,
 * and user devices.
 * Part of the Security Officer Dashboard workflow: View → API → ViewModel → Repository
 *
 * @module services/security
 */

import { get } from './api.js';

// ============================================================================
// Types
// ============================================================================

/**
 * Security statistics for the security officer dashboard
 */
export interface SecurityStats {
  /** Failed login attempts in last 24 hours */
  failedLogins24h: number;
  /** Currently active user sessions */
  activeSessions: number;
  /** Percentage of users with MFA enabled */
  mfaCompliance: number;
  /** Number of security anomalies detected */
  anomaliesDetected: number;
  /** Total active users */
  totalUsers: number;
  /** Users with MFA enabled */
  usersWithMFA: number;
  /** Currently locked accounts */
  lockedAccounts: number;
}

/**
 * Audit event entry
 */
export interface AuditEvent {
  /** Audit event UUID */
  id: string;
  /** Type of event (action performed) */
  eventType: string;
  /** User ID if applicable */
  userId: string | null;
  /** Username */
  userName: string | null;
  /** IP address (partially masked) */
  ipAddress: string | null;
  /** User agent string */
  userAgent: string | null;
  /** Event timestamp (ISO 8601 format) */
  timestamp: string;
  /** Event details */
  details: string | null;
  /** Whether this event has been flagged */
  flagged: boolean;
  /** Session ID (partially masked) */
  sessionId: string | null;
  /** Subject type */
  subjectType: string | null;
  /** Subject ID */
  subjectId: string | null;
}

/**
 * Failed login attempt
 */
export interface FailedLogin {
  /** Log entry UUID */
  id: string;
  /** User ID if applicable */
  userId: string | null;
  /** Username */
  userName: string | null;
  /** IP address (partially masked) */
  ipAddress: string | null;
  /** User agent string */
  userAgent: string | null;
  /** Attempt timestamp (ISO 8601 format) */
  timestamp: string;
  /** Number of consecutive failed attempts */
  attemptCount: number;
  /** Additional details */
  details: string | null;
}

/**
 * MFA user status
 */
export interface MFAUser {
  /** User UUID */
  userId: string;
  /** Username */
  userName: string;
  /** User email */
  email: string;
  /** Whether MFA is enabled */
  mfaEnabled: boolean;
  /** MFA status (enabled, disabled, pending) */
  mfaStatus: 'enabled' | 'disabled' | 'pending';
  /** Last login timestamp (ISO 8601 format) */
  lastLogin: string | null;
  /** User's role */
  role: string;
}

/**
 * MFA status overview
 */
export interface MFAStatus {
  /** Number of users with MFA enabled */
  enabled: number;
  /** Number of users with MFA disabled */
  disabled: number;
  /** Number of users with pending MFA setup */
  pending: number;
  /** MFA compliance rate percentage */
  complianceRate: number;
  /** List of users with MFA status */
  users: MFAUser[];
}

/**
 * Active session information
 */
export interface ActiveSession {
  /** Session UUID */
  id: string;
  /** User UUID */
  userId: string;
  /** Username */
  userName: string;
  /** User email */
  email: string;
  /** IP address (partially masked) */
  ipAddress: string | null;
  /** Last activity timestamp (ISO 8601 format) */
  lastActivity: string;
  /** User's role */
  role: string;
}

/**
 * Security alert information
 */
export interface SecurityAlert {
  /** Alert UUID */
  id: string;
  /** Alert type (audit_flag, compliance, etc.) */
  alertType: string;
  /** Severity level */
  severity: 'warning' | 'critical';
  /** Alert message */
  message: string;
  /** Alert timestamp (ISO 8601 format) */
  timestamp: string;
  /** Whether the alert has been acknowledged */
  acknowledged: boolean;
  /** When acknowledged (ISO 8601 format) */
  acknowledgedAt: string | null;
}

/**
 * User device information
 */
export interface UserDevice {
  /** Device UUID */
  id: string;
  /** User UUID */
  userId: string | null;
  /** Username */
  userName: string | null;
  /** Device platform */
  platform: string | null;
  /** Device status */
  status: string | null;
  /** Last seen timestamp (ISO 8601 format) */
  lastSeen: string | null;
  /** Whether device is encrypted at rest */
  encryptedAtRest: boolean;
}

/**
 * Complete security officer dashboard data
 */
export interface SecurityOfficerDashboardData {
  /** Security statistics */
  stats: SecurityStats;
  /** Audit events */
  auditEvents: AuditEvent[];
  /** Failed login attempts */
  failedLogins: FailedLogin[];
  /** MFA status overview */
  mfaStatus: MFAStatus;
  /** Active sessions */
  activeSessions: ActiveSession[];
  /** Security alerts */
  securityAlerts: SecurityAlert[];
}

// ============================================================================
// API Response Types
// ============================================================================

interface DashboardResponse {
  stats: SecurityStats;
  auditEvents: AuditEvent[];
  failedLogins: FailedLogin[];
  mfaStatus: MFAStatus;
  activeSessions: ActiveSession[];
  securityAlerts: SecurityAlert[];
}

interface StatsResponse {
  stats: SecurityStats;
}

interface AuditEventsResponse {
  auditEvents: AuditEvent[];
  count: number;
}

interface FailedLoginsResponse {
  failedLogins: FailedLogin[];
  count: number;
}

interface MFAStatusResponse {
  mfaStatus: MFAStatus;
}

interface ActiveSessionsResponse {
  activeSessions: ActiveSession[];
  count: number;
}

interface SecurityAlertsResponse {
  securityAlerts: SecurityAlert[];
  count: number;
}

interface UserDevicesResponse {
  userDevices: UserDevice[];
  count: number;
}

// ============================================================================
// Service Functions
// ============================================================================

/**
 * Get complete security officer dashboard data
 * 
 * Fetches security stats, audit events, failed logins, MFA status,
 * active sessions, and security alerts in a single call.
 * 
 * @returns Promise resolving to complete dashboard data
 * 
 * @example
 * ```typescript
 * const dashboardData = await getDashboardData();
 * console.log(`Failed Logins: ${dashboardData.stats.failedLogins24h}`);
 * console.log(`MFA Compliance: ${dashboardData.stats.mfaCompliance}%`);
 * ```
 */
export async function getDashboardData(): Promise<SecurityOfficerDashboardData> {
  const response = await get<DashboardResponse>('/security/dashboard');
  return {
    stats: response.data.stats,
    auditEvents: response.data.auditEvents,
    failedLogins: response.data.failedLogins,
    mfaStatus: response.data.mfaStatus,
    activeSessions: response.data.activeSessions,
    securityAlerts: response.data.securityAlerts,
  };
}

/**
 * Get security statistics only
 * 
 * Fetches just the security metrics for quick status updates or polling.
 * 
 * @returns Promise resolving to security statistics
 * 
 * @example
 * ```typescript
 * const stats = await getSecurityStats();
 * console.log(`Active Sessions: ${stats.activeSessions}`);
 * ```
 */
export async function getSecurityStats(): Promise<SecurityStats> {
  const response = await get<StatsResponse>('/security/stats');
  return response.data.stats;
}

/**
 * Get audit events
 * 
 * Fetches recent security-related audit events.
 * 
 * @param limit - Maximum number of results (default: 20, max: 100)
 * @returns Promise resolving to array of audit events
 * 
 * @example
 * ```typescript
 * const events = await getAuditEvents(50);
 * events.forEach(event => console.log(`${event.eventType} - ${event.userName}`));
 * ```
 */
export async function getAuditEvents(limit?: number): Promise<AuditEvent[]> {
  const params = limit ? `?limit=${Math.min(Math.max(1, limit), 100)}` : '';
  const response = await get<AuditEventsResponse>(`/security/audit${params}`);
  return response.data.auditEvents;
}

/**
 * Get failed login attempts
 * 
 * Fetches recent failed login attempts for security monitoring.
 * 
 * @param limit - Maximum number of results (default: 20, max: 100)
 * @returns Promise resolving to array of failed login attempts
 * 
 * @example
 * ```typescript
 * const failedLogins = await getFailedLoginAttempts();
 * const byIp = failedLogins.reduce((acc, login) => {
 *   acc[login.ipAddress || 'unknown'] = (acc[login.ipAddress || 'unknown'] || 0) + 1;
 *   return acc;
 * }, {} as Record<string, number>);
 * ```
 */
export async function getFailedLoginAttempts(limit?: number): Promise<FailedLogin[]> {
  const params = limit ? `?limit=${Math.min(Math.max(1, limit), 100)}` : '';
  const response = await get<FailedLoginsResponse>(`/security/failed-logins${params}`);
  return response.data.failedLogins;
}

/**
 * Get MFA status
 * 
 * Fetches MFA enrollment and compliance status across all users.
 * 
 * @returns Promise resolving to MFA status overview
 * 
 * @example
 * ```typescript
 * const mfa = await getMFAStatus();
 * console.log(`MFA Compliance: ${mfa.complianceRate}%`);
 * console.log(`Users with MFA: ${mfa.enabled}/${mfa.enabled + mfa.disabled + mfa.pending}`);
 * ```
 */
export async function getMFAStatus(): Promise<MFAStatus> {
  const response = await get<MFAStatusResponse>('/security/mfa');
  return response.data.mfaStatus;
}

/**
 * Get active sessions
 * 
 * Fetches currently active user sessions.
 * 
 * @param limit - Maximum number of results (default: 20, max: 100)
 * @returns Promise resolving to array of active sessions
 * 
 * @example
 * ```typescript
 * const sessions = await getActiveSessions();
 * console.log(`Active Users: ${sessions.length}`);
 * ```
 */
export async function getActiveSessions(limit?: number): Promise<ActiveSession[]> {
  const params = limit ? `?limit=${Math.min(Math.max(1, limit), 100)}` : '';
  const response = await get<ActiveSessionsResponse>(`/security/sessions${params}`);
  return response.data.activeSessions;
}

/**
 * Get security alerts
 * 
 * Fetches security alerts and anomalies.
 * 
 * @param limit - Maximum number of results (default: 20, max: 100)
 * @returns Promise resolving to array of security alerts
 * 
 * @example
 * ```typescript
 * const alerts = await getSecurityAlerts();
 * const critical = alerts.filter(a => a.severity === 'critical');
 * console.log(`Critical Alerts: ${critical.length}`);
 * ```
 */
export async function getSecurityAlerts(limit?: number): Promise<SecurityAlert[]> {
  const params = limit ? `?limit=${Math.min(Math.max(1, limit), 100)}` : '';
  const response = await get<SecurityAlertsResponse>(`/security/alerts${params}`);
  return response.data.securityAlerts;
}

/**
 * Get user devices
 * 
 * Fetches registered user devices with security status.
 * 
 * @param limit - Maximum number of results (default: 20, max: 100)
 * @returns Promise resolving to array of user devices
 * 
 * @example
 * ```typescript
 * const devices = await getUserDevices();
 * const unencrypted = devices.filter(d => !d.encryptedAtRest);
 * console.log(`Unencrypted devices: ${unencrypted.length}`);
 * ```
 */
export async function getUserDevices(limit?: number): Promise<UserDevice[]> {
  const params = limit ? `?limit=${Math.min(Math.max(1, limit), 100)}` : '';
  const response = await get<UserDevicesResponse>(`/security/devices${params}`);
  return response.data.userDevices;
}

// ============================================================================
// Export Default
// ============================================================================

/**
 * Security Officer service object containing all security officer-related methods
 */
export const securityService = {
  getDashboardData,
  getSecurityStats,
  getAuditEvents,
  getFailedLoginAttempts,
  getMFAStatus,
  getActiveSessions,
  getSecurityAlerts,
  getUserDevices,
} as const;

export default securityService;
