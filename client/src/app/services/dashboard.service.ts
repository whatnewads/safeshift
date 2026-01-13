/**
 * Dashboard Service for SafeShift EHR
 *
 * Provides API methods for fetching dashboard data, stats, alerts, and recent activity.
 */

import { get, post, put } from './api.js';

// ============================================================================
// Types
// ============================================================================

export interface ManagerStats {
  openCases: number;
  followUpsDue: number;
  highRisk: number;
  closedThisMonth: number;
}

export interface Case {
  id: string;
  encounterId: string;
  patient: string;
  patientId: string;
  patientMrn?: string;
  type: string;
  encounterType: string;
  status: 'open' | 'follow-up-due' | 'high-risk' | 'closed';
  encounterStatus: string;
  oshaStatus: 'pending' | 'submitted' | 'not-applicable';
  days: number;
  daysOpen: number;
  activeFlags: string[];
  chiefComplaint?: string;
  encounterDate?: string;
  createdAt?: string;
}

export interface CaseFlag {
  flag_id: string;
  flag_type: string;
  flag_reason: string;
  severity: string;
  created_at: string;
  is_resolved: boolean;
  resolved_at?: string;
}

export interface ManagerDashboardData {
  stats: ManagerStats;
  cases: Case[];
}

export interface ClinicalProviderStats {
  inProgress: number;
  pendingReview: number;
  completedToday: number;
  todaysTotal: number;
}

export interface TechnicianStats {
  pendingTasks: number;
  completedToday: number;
}

export interface RegistrationStats {
  scheduledToday: number;
  checkedIn: number;
  total: number;
}

// ============================================================================
// Dashboard Functions
// ============================================================================

/**
 * Fetch role-specific dashboard data
 * @returns Promise resolving to dashboard data
 */
export async function getDashboard<T = ManagerDashboardData>(): Promise<T> {
  const response = await get<T>('/dashboard');
  return response.data;
}

/**
 * Fetch manager dashboard specifically
 * @returns Promise resolving to manager dashboard data
 */
export async function getManagerDashboard(): Promise<ManagerDashboardData> {
  const response = await get<ManagerDashboardData>('/dashboard/manager');
  return response.data;
}

/**
 * Fetch dashboard statistics only
 * @returns Promise resolving to dashboard stats
 */
export async function getDashboardStats(): Promise<{ stats: ManagerStats }> {
  const response = await get<{ stats: ManagerStats }>('/dashboard/stats');
  return response.data;
}

/**
 * Fetch cases with optional filtering
 * @param status - Filter by case status
 * @param page - Page number
 * @param perPage - Items per page
 * @returns Promise resolving to cases list with pagination
 */
export async function getCases(
  status?: string,
  page = 1,
  perPage = 20
): Promise<{
  cases: Case[];
  pagination: {
    page: number;
    per_page: number;
    total: number;
    total_pages: number;
  };
}> {
  const params: Record<string, string | number | undefined> = {
    page,
    per_page: perPage,
  };
  if (status) {
    params.status = status;
  }
  
  const response = await get<{
    cases: Case[];
    pagination: {
      page: number;
      per_page: number;
      total: number;
      total_pages: number;
    };
  }>('/dashboard/cases', { params });
  return response.data;
}

/**
 * Fetch a single case by ID
 * @param caseId - Case/encounter ID
 * @returns Promise resolving to case details
 */
export async function getCase(caseId: string): Promise<{ case: Case & { flags?: CaseFlag[]; osha?: object } }> {
  const response = await get<{ case: Case & { flags?: CaseFlag[]; osha?: object } }>(`/dashboard/cases/${caseId}`);
  return response.data;
}

/**
 * Add a flag to a case
 * @param caseId - Case/encounter ID
 * @param flagType - Type of flag
 * @param reason - Reason for flagging
 * @param severity - Flag severity
 * @returns Promise resolving to updated case
 */
export async function addCaseFlag(
  caseId: string,
  flagType: string,
  reason: string,
  severity = 'medium'
): Promise<{ case: Case; message: string }> {
  const response = await post<{ case: Case; message: string }>(`/dashboard/cases/${caseId}/flags`, {
    flag_type: flagType,
    reason,
    severity,
  });
  return response.data;
}

/**
 * Resolve a flag
 * @param flagId - Flag ID to resolve
 * @returns Promise resolving to success message
 */
export async function resolveFlag(flagId: string): Promise<{ message: string }> {
  const response = await put<{ message: string }>(`/dashboard/flags/${flagId}/resolve`, {});
  return response.data;
}

/**
 * Get clinical provider dashboard
 */
export async function getClinicalProviderDashboard(): Promise<{
  stats: ClinicalProviderStats;
  pendingEncounters: object[];
  todaysEncounters: object[];
}> {
  const response = await get<{
    stats: ClinicalProviderStats;
    pendingEncounters: object[];
    todaysEncounters: object[];
  }>('/dashboard/clinical');
  return response.data;
}

/**
 * Get technician dashboard
 */
export async function getTechnicianDashboard(): Promise<{
  stats: TechnicianStats;
  taskQueue: object[];
}> {
  const response = await get<{
    stats: TechnicianStats;
    taskQueue: object[];
  }>('/dashboard/technician');
  return response.data;
}

/**
 * Get registration dashboard
 */
export async function getRegistrationDashboard(): Promise<{
  stats: RegistrationStats;
  appointments: object[];
}> {
  const response = await get<{
    stats: RegistrationStats;
    appointments: object[];
  }>('/dashboard/registration');
  return response.data;
}

// ============================================================================
// Service Object Export
// ============================================================================

/**
 * Dashboard service object with all dashboard-related API methods
 */
export const dashboardService = {
  getDashboard,
  getManagerDashboard,
  getStats: getDashboardStats,
  getCases,
  getCase,
  addCaseFlag,
  resolveFlag,
  getClinicalProviderDashboard,
  getTechnicianDashboard,
  getRegistrationDashboard,
};

export default dashboardService;
