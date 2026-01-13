/**
 * SuperManager Service
 *
 * Handles API calls for SuperManager dashboard including multi-clinic oversight,
 * staff management, operational metrics, and approval actions.
 * Part of the SuperManager Dashboard workflow: View → API → ViewModel → Repository
 *
 * The SuperManager role is associated with 'Manager' role type with elevated
 * permissions across multiple establishments/clinics.
 *
 * @module services/supermanager
 */

import { get, post } from './api.js';

// ============================================================================
// Types
// ============================================================================

/**
 * SuperManager overview statistics for the dashboard
 */
export interface SuperManagerStats {
  /** Number of clinics/establishments managed */
  clinicsManaged: number;
  /** Total active staff members */
  totalStaff: number;
  /** Total encounters across all clinics today */
  encountersToday: number;
  /** Number of pending approval requests */
  pendingApprovals: number;
}

/**
 * Clinic performance metrics
 */
export interface ClinicPerformance {
  /** Clinic/establishment UUID */
  id: string;
  /** Clinic name */
  name: string;
  /** Number of encounters today */
  encountersToday: number;
  /** Average wait time in minutes */
  avgWaitTime: number;
  /** Patient satisfaction score (0-5 scale) */
  patientSatisfaction: number;
  /** Number of staff members */
  staffCount: number;
  /** Clinic status */
  status: 'active' | 'inactive';
  /** Clinic location */
  location?: string;
}

/**
 * Staff member overview
 */
export interface StaffMember {
  /** Staff/user UUID */
  id: string;
  /** Staff name */
  name: string;
  /** Staff email */
  email?: string;
  /** Assigned clinic */
  clinic: string;
  /** Role(s) */
  role: string;
  /** Training compliance status */
  trainingStatus: 'compliant' | 'expiring' | 'overdue' | 'unknown';
  /** Credential status */
  credentialStatus: 'valid' | 'expiring' | 'expired' | 'unknown';
  /** Last login timestamp */
  lastLogin?: string | null;
}

/**
 * Expiring credential alert
 */
export interface ExpiringCredential {
  /** Record UUID */
  id: string;
  /** Staff/user UUID */
  staffId: string;
  /** Staff name */
  staffName: string;
  /** Credential name */
  credential: string;
  /** Expiration date (ISO 8601 format) */
  expiresAt: string;
  /** Days until expiration */
  daysUntilExpiry: number;
  /** Assigned clinic */
  clinic: string;
}

/**
 * Overdue training alert
 */
export interface OverdueTraining {
  /** Record UUID */
  id: string;
  /** Staff/user UUID */
  staffId: string;
  /** Staff name */
  staffName: string;
  /** Training name */
  training: string;
  /** Due date (ISO 8601 format) */
  dueDate: string;
  /** Days overdue */
  daysOverdue: number;
  /** Assigned clinic */
  clinic: string;
}

/**
 * Pending approval request
 */
export interface PendingApproval {
  /** Request UUID */
  id: string;
  /** Request type */
  type: 'time_off' | 'schedule_change' | 'chart_review';
  /** Staff member name */
  staffName: string;
  /** Request date (ISO 8601 format) */
  requestDate: string;
  /** Request details */
  details: string;
  /** Request status */
  status: 'pending' | 'approved' | 'denied';
}

/**
 * Clinic comparison metrics
 */
export interface ClinicComparison {
  /** Clinic/establishment UUID */
  clinicId: string;
  /** Clinic name */
  clinicName: string;
  /** Encounters in last 7 days */
  encounters7d: number;
  /** Encounters in last 30 days */
  encounters30d: number;
  /** Average wait time in minutes */
  avgWaitTime: number;
  /** Average treatment time in minutes */
  avgTreatmentTime: number;
  /** Number of staff members */
  staffCount: number;
}

/**
 * Staff requiring attention (credentials + training)
 */
export interface StaffRequiringAttention {
  /** Expiring credentials list */
  expiringCredentials: ExpiringCredential[];
  /** Overdue training list */
  overdueTraining: OverdueTraining[];
}

/**
 * Complete SuperManager dashboard data
 */
export interface SuperManagerDashboardData {
  /** Overview statistics */
  stats: SuperManagerStats;
  /** Clinic performance metrics */
  clinicPerformance: ClinicPerformance[];
  /** Staff requiring attention */
  staffRequiringAttention: StaffRequiringAttention;
  /** Pending approvals */
  pendingApprovals: PendingApproval[];
  /** Staff overview */
  staffOverview: StaffMember[];
}

// ============================================================================
// API Response Types
// ============================================================================

interface DashboardResponse {
  stats: SuperManagerStats;
  clinicPerformance: ClinicPerformance[];
  staffRequiringAttention: StaffRequiringAttention;
  pendingApprovals: PendingApproval[];
  staffOverview: StaffMember[];
}

interface StatsResponse {
  stats: SuperManagerStats;
}

interface ClinicsResponse {
  clinics: ClinicPerformance[];
  count: number;
}

interface ComparisonResponse {
  comparison: ClinicComparison[];
  count: number;
}

interface StaffResponse {
  staff: StaffMember[];
  count: number;
}

interface CredentialsResponse {
  credentials: ExpiringCredential[];
  count: number;
}

interface TrainingResponse {
  overdue: OverdueTraining[];
  count: number;
}

interface ApprovalsResponse {
  approvals: PendingApproval[];
  count: number;
}

interface ApprovalActionResponse {
  message: string;
  requestId: string;
  type: string;
}

// ============================================================================
// Service Functions
// ============================================================================

/**
 * Get complete SuperManager dashboard data
 * 
 * Fetches overview stats, clinic performance, staff attention alerts,
 * pending approvals, and staff overview in a single call.
 * 
 * @returns Promise resolving to complete dashboard data
 * 
 * @example
 * ```typescript
 * const dashboardData = await getDashboardData();
 * console.log(`Clinics Managed: ${dashboardData.stats.clinicsManaged}`);
 * console.log(`Staff: ${dashboardData.staffOverview.length}`);
 * ```
 */
export async function getDashboardData(): Promise<SuperManagerDashboardData> {
  const response = await get<DashboardResponse>('/supermanager/dashboard');
  return {
    stats: response.data.stats,
    clinicPerformance: response.data.clinicPerformance,
    staffRequiringAttention: response.data.staffRequiringAttention,
    pendingApprovals: response.data.pendingApprovals,
    staffOverview: response.data.staffOverview,
  };
}

/**
 * Get SuperManager overview statistics only
 * 
 * Fetches just the overview counts for quick status updates or polling.
 * 
 * @returns Promise resolving to overview statistics
 * 
 * @example
 * ```typescript
 * const stats = await getOverviewStats();
 * console.log(`${stats.pendingApprovals} approvals pending`);
 * ```
 */
export async function getOverviewStats(): Promise<SuperManagerStats> {
  const response = await get<StatsResponse>('/supermanager/stats');
  return response.data.stats;
}

/**
 * Get clinic performance metrics
 * 
 * Fetches performance metrics for all managed clinics.
 * 
 * @param limit - Maximum number of results (default: 10, max: 50)
 * @returns Promise resolving to array of clinic performance data
 * 
 * @example
 * ```typescript
 * const clinics = await getClinicPerformance(10);
 * clinics.forEach(c => console.log(`${c.name}: ${c.encountersToday} encounters`));
 * ```
 */
export async function getClinicPerformance(limit?: number): Promise<ClinicPerformance[]> {
  const params = limit ? `?limit=${Math.min(Math.max(1, limit), 50)}` : '';
  const response = await get<ClinicsResponse>(`/supermanager/clinics${params}`);
  return response.data.clinics;
}

/**
 * Get clinic comparison metrics
 * 
 * Fetches comparative metrics across all clinics.
 * 
 * @returns Promise resolving to array of clinic comparison data
 * 
 * @example
 * ```typescript
 * const comparison = await getClinicComparison();
 * comparison.forEach(c => console.log(`${c.clinicName}: ${c.encounters30d} encounters (30d)`));
 * ```
 */
export async function getClinicComparison(): Promise<ClinicComparison[]> {
  const response = await get<ComparisonResponse>('/supermanager/clinics/comparison');
  return response.data.comparison;
}

/**
 * Get staff overview list
 * 
 * Fetches staff members with roles and compliance status.
 * 
 * @param limit - Maximum number of results (default: 20, max: 100)
 * @returns Promise resolving to array of staff members
 * 
 * @example
 * ```typescript
 * const staff = await getStaffOverview();
 * staff.forEach(s => console.log(`${s.name} - ${s.role} - ${s.trainingStatus}`));
 * ```
 */
export async function getStaffOverview(limit?: number): Promise<StaffMember[]> {
  const params = limit ? `?limit=${Math.min(Math.max(1, limit), 100)}` : '';
  const response = await get<StaffResponse>(`/supermanager/staff${params}`);
  return response.data.staff;
}

/**
 * Get expiring credentials list
 * 
 * Fetches credentials expiring within specified days.
 * 
 * @param daysAhead - Days to look ahead (default: 30, max: 365)
 * @param limit - Maximum number of results (default: 10, max: 50)
 * @returns Promise resolving to array of expiring credentials
 * 
 * @example
 * ```typescript
 * const credentials = await getExpiringCredentials(30, 10);
 * credentials.forEach(c => console.log(`${c.staffName} - ${c.credential} expires ${c.expiresAt}`));
 * ```
 */
export async function getExpiringCredentials(daysAhead?: number, limit?: number): Promise<ExpiringCredential[]> {
  const params = new URLSearchParams();
  if (daysAhead) params.set('days', String(Math.min(Math.max(1, daysAhead), 365)));
  if (limit) params.set('limit', String(Math.min(Math.max(1, limit), 50)));
  const queryString = params.toString() ? `?${params.toString()}` : '';
  const response = await get<CredentialsResponse>(`/supermanager/credentials/expiring${queryString}`);
  return response.data.credentials;
}

/**
 * Get overdue training list
 * 
 * Fetches staff with overdue training.
 * 
 * @param limit - Maximum number of results (default: 10, max: 50)
 * @returns Promise resolving to array of overdue training alerts
 * 
 * @example
 * ```typescript
 * const overdue = await getTrainingOverdue();
 * overdue.forEach(o => console.log(`${o.staffName} - ${o.training} ${o.daysOverdue} days overdue`));
 * ```
 */
export async function getTrainingOverdue(limit?: number): Promise<OverdueTraining[]> {
  const params = limit ? `?limit=${Math.min(Math.max(1, limit), 50)}` : '';
  const response = await get<TrainingResponse>(`/supermanager/training/overdue${params}`);
  return response.data.overdue;
}

/**
 * Get pending approvals list
 * 
 * Fetches pending approval requests (time off, schedule changes, etc.)
 * 
 * @param limit - Maximum number of results (default: 10, max: 50)
 * @returns Promise resolving to array of pending approvals
 * 
 * @example
 * ```typescript
 * const approvals = await getPendingApprovals();
 * approvals.forEach(a => console.log(`${a.staffName} - ${a.type} - ${a.details}`));
 * ```
 */
export async function getPendingApprovals(limit?: number): Promise<PendingApproval[]> {
  const params = limit ? `?limit=${Math.min(Math.max(1, limit), 50)}` : '';
  const response = await get<ApprovalsResponse>(`/supermanager/approvals${params}`);
  return response.data.approvals;
}

/**
 * Approve a pending request
 * 
 * Approves a pending approval request.
 * 
 * @param requestId - Request UUID
 * @param type - Request type ('time_off', 'schedule_change', 'chart_review')
 * @returns Promise resolving when approval is submitted
 * 
 * @example
 * ```typescript
 * await approvePending('abc-123-def', 'time_off');
 * ```
 */
export async function approvePending(requestId: string, type: string): Promise<void> {
  await post<ApprovalActionResponse>(`/supermanager/approve/${requestId}`, { type });
}

/**
 * Deny a pending request
 * 
 * Denies a pending approval request with optional reason.
 * 
 * @param requestId - Request UUID
 * @param type - Request type ('time_off', 'schedule_change', 'chart_review')
 * @param reason - Optional denial reason
 * @returns Promise resolving when denial is submitted
 * 
 * @example
 * ```typescript
 * await denyPending('abc-123-def', 'time_off', 'Insufficient staffing coverage.');
 * ```
 */
export async function denyPending(requestId: string, type: string, reason?: string): Promise<void> {
  await post<ApprovalActionResponse>(`/supermanager/deny/${requestId}`, { type, reason: reason || '' });
}

// ============================================================================
// Export Default
// ============================================================================

/**
 * SuperManager service object containing all supermanager-related methods
 */
export const supermanagerService = {
  getDashboardData,
  getOverviewStats,
  getClinicPerformance,
  getClinicComparison,
  getStaffOverview,
  getExpiringCredentials,
  getTrainingOverdue,
  getPendingApprovals,
  approvePending,
  denyPending,
} as const;

export default supermanagerService;
