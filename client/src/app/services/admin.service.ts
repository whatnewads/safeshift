/**
 * Admin Service
 *
 * Handles API calls for admin dashboard, compliance, training, and OSHA data.
 * Part of the Admin Dashboard workflow: View → API → ViewModel → Model
 *
 * @module services/admin
 */

import { get, put } from './api.js';

// ============================================================================
// Types
// ============================================================================

export interface AdminStats {
  pendingQA: number;
  openCases: number;
  pendingOrders: number;
  encountersToday: number;
  followUpsDue: number;
  highRisk: number;
  complianceAlertsCount: number;
  expiringCredentialsCount: number;
  trainingDueCount: number;
  regulatoryUpdates: number;
}

export interface PatientFlow {
  walkIns: number;
  scheduled: number;
  emergency: number;
  totalToday: number;
}

export interface RecentCase {
  id: string;
  patientName: string;
  patientId: string;
  chiefComplaint: string;
  status: string;
  encounterType: string;
  assignedProvider: string;
  daysOpen: number;
  createdAt: string;
  occurredOn: string;
}

export interface SitePerformance {
  siteId: string;
  siteName: string;
  city: string;
  state: string;
  encountersToday: number;
  encounters30d: number;
  avgTreatmentTime: number;
  avgWaitTime: number;
  avgEncounterTime?: number;
  patientSatisfaction: number;
}

export interface ProviderPerformance {
  providerId: string;
  providerName: string;
  role: string;
  encountersToday: number;
  encounters30d: number;
  avgEncounterTime: number;
  qaScore: number;
  completionRate?: number;
}

export interface StaffMember {
  id: string;
  username: string;
  email: string;
  status: string;
  isActive: boolean;
  roles: string;
  roleSlugs: string[];
  lastLogin: string | null;
  createdAt: string;
}

export interface ClearanceStats {
  cleared: number;
  notCleared: number;
  pendingReview: number;
}

export interface AdminDashboardData {
  stats: AdminStats;
  patientFlow: PatientFlow;
  recentCases: RecentCase[];
  sitePerformance: SitePerformance[];
  providerPerformance: ProviderPerformance[];
  clearanceStats: ClearanceStats;
}

export interface ComplianceAlert {
  id: string;
  type: string;
  title: string;
  description: string;
  priority: 'high' | 'medium' | 'low';
  status: 'active' | 'acknowledged' | 'resolved';
  createdAt: string;
  acknowledgedAt: string | null;
  acknowledgedBy: string | null;
  relatedEntityType: string | null;
  relatedEntityId: string | null;
}

export interface TrainingModule {
  id: string;
  title: string;
  description: string | null;
  frequencyDays: number;
  isRequired: boolean;
  assignedTo: number;
  completed: number;
  inProgress: number;
  notStarted: number;
  expiringCount: number;
}

export interface ExpiringCredential {
  id: string;
  userId: string;
  user: string;
  credential: string;
  expiryDate: string;
  daysUntilExpiry: number;
  status: string;
}

export interface Osha300Entry {
  entry_id: string;
  case_number: string;
  employee_name: string;
  job_title: string;
  date_of_injury: string;
  injury_description: string;
  location_of_incident: string;
  injury_type: string;
  death: boolean;
  days_away: boolean;
  job_transfer: boolean;
  other_recordable: boolean;
  days_away_count: number;
  days_restricted_count: number;
}

export interface OshaStatistics {
  totalRecordableCases: number;
  daysAwayCases: number;
  jobTransferCases: number;
  otherRecordableCases: number;
  totalDaysAway: number;
  totalDaysRestricted: number;
}

export interface Osha300AEntry {
  year: number;
  establishment_name: string;
  establishment_address: string;
  industry_description: string;
  sic_code: string;
  annual_average_employees: number;
  total_hours_worked: number;
  total_deaths: number;
  total_days_away_cases: number;
  total_job_transfer_cases: number;
  total_other_recordable_cases: number;
  total_injuries: number;
  total_skin_disorders: number;
  total_respiratory_conditions: number;
  total_poisonings: number;
  total_hearing_loss: number;
  total_all_other_illnesses: number;
}

// ============================================================================
// API Response Types
// ============================================================================

interface AdminDashboardResponse {
  stats: AdminStats;
  patientFlow: PatientFlow;
  recentCases: RecentCase[];
  sitePerformance: SitePerformance[];
  providerPerformance: ProviderPerformance[];
  clearanceStats: ClearanceStats;
}

interface CaseStatsResponse {
  stats: AdminStats;
}

interface RecentCasesResponse {
  cases: RecentCase[];
  count: number;
}

interface PatientFlowResponse {
  patientFlow: PatientFlow;
}

interface SitePerformanceResponse {
  sites: SitePerformance[];
  count: number;
}

interface ProviderPerformanceResponse {
  providers: ProviderPerformance[];
  count: number;
}

interface StaffListResponse {
  staff: StaffMember[];
  pagination: {
    page: number;
    per_page: number;
    total: number;
    total_pages: number;
  };
}

interface ClearanceStatsResponse {
  clearanceStats: ClearanceStats;
}

interface ComplianceAlertsResponse {
  alerts: ComplianceAlert[];
  pagination: {
    page: number;
    per_page: number;
    total: number;
    total_pages: number;
  };
}

interface TrainingModulesResponse {
  modules: TrainingModule[];
}

interface ExpiringCredentialsResponse {
  credentials: ExpiringCredential[];
  count: number;
}

interface Osha300Response {
  entries: Osha300Entry[];
  statistics: OshaStatistics;
  year: number;
  readonly: boolean;
}

interface Osha300AResponse {
  summary: Osha300AEntry | null;
  year: number;
  readonly: boolean;
}

// ============================================================================
// Service Functions
// ============================================================================

/**
 * Get full admin dashboard data (all data in one call)
 */
export async function getAdminDashboard(): Promise<AdminDashboardData> {
  const response = await get<AdminDashboardResponse>('/admin');
  return {
    stats: response.data.stats,
    patientFlow: response.data.patientFlow,
    recentCases: response.data.recentCases,
    sitePerformance: response.data.sitePerformance,
    providerPerformance: response.data.providerPerformance,
    clearanceStats: response.data.clearanceStats,
  };
}

/**
 * Get case statistics only
 */
export async function getCaseStats(): Promise<AdminStats> {
  const response = await get<CaseStatsResponse>('/admin/stats');
  return response.data.stats;
}

/**
 * Get recent cases
 */
export async function getRecentCases(limit?: number): Promise<RecentCase[]> {
  const url = `/admin/cases${limit ? `?limit=${limit}` : ''}`;
  const response = await get<RecentCasesResponse>(url);
  return response.data.cases;
}

/**
 * Get patient flow metrics
 */
export async function getPatientFlowMetrics(): Promise<PatientFlow> {
  const response = await get<PatientFlowResponse>('/admin/patient-flow');
  return response.data.patientFlow;
}

/**
 * Get site performance metrics
 */
export async function getSitePerformance(limit?: number): Promise<SitePerformance[]> {
  const url = `/admin/sites${limit ? `?limit=${limit}` : ''}`;
  const response = await get<SitePerformanceResponse>(url);
  return response.data.sites;
}

/**
 * Get provider performance metrics
 */
export async function getProviderPerformance(limit?: number): Promise<ProviderPerformance[]> {
  const url = `/admin/providers${limit ? `?limit=${limit}` : ''}`;
  const response = await get<ProviderPerformanceResponse>(url);
  return response.data.providers;
}

/**
 * Get staff list with pagination
 */
export async function getStaffList(params?: {
  page?: number;
  perPage?: number;
}): Promise<{
  staff: StaffMember[];
  pagination: StaffListResponse['pagination'];
}> {
  const queryParams = new URLSearchParams();
  if (params?.page) queryParams.append('page', params.page.toString());
  if (params?.perPage) queryParams.append('per_page', params.perPage.toString());

  const queryString = queryParams.toString();
  const url = `/admin/staff${queryString ? `?${queryString}` : ''}`;
  
  const response = await get<StaffListResponse>(url);
  return {
    staff: response.data.staff,
    pagination: response.data.pagination,
  };
}

/**
 * Get clearance statistics
 */
export async function getClearanceStats(): Promise<ClearanceStats> {
  const response = await get<ClearanceStatsResponse>('/admin/clearance');
  return response.data.clearanceStats;
}

/**
 * Get compliance alerts
 */
export async function getComplianceAlerts(params?: {
  status?: string;
  priority?: string;
  page?: number;
  perPage?: number;
}): Promise<{
  alerts: ComplianceAlert[];
  pagination: ComplianceAlertsResponse['pagination'];
}> {
  const queryParams = new URLSearchParams();
  if (params?.status) queryParams.append('status', params.status);
  if (params?.priority) queryParams.append('priority', params.priority);
  if (params?.page) queryParams.append('page', params.page.toString());
  if (params?.perPage) queryParams.append('per_page', params.perPage.toString());

  const queryString = queryParams.toString();
  const url = `/admin/compliance${queryString ? `?${queryString}` : ''}`;
  
  const response = await get<ComplianceAlertsResponse>(url);
  return {
    alerts: response.data.alerts,
    pagination: response.data.pagination,
  };
}

/**
 * Acknowledge a compliance alert
 */
export async function acknowledgeComplianceAlert(alertId: string): Promise<void> {
  await put(`/admin/compliance/${alertId}/acknowledge`, {});
}

/**
 * Get training modules summary
 */
export async function getTrainingModules(): Promise<TrainingModule[]> {
  const response = await get<TrainingModulesResponse>('/admin/training');
  return response.data.modules;
}

/**
 * Get expiring credentials
 */
export async function getExpiringCredentials(daysAhead?: number): Promise<{
  credentials: ExpiringCredential[];
  count: number;
}> {
  const url = `/admin/credentials${daysAhead ? `?days=${daysAhead}` : ''}`;
  const response = await get<ExpiringCredentialsResponse>(url);
  return {
    credentials: response.data.credentials,
    count: response.data.count,
  };
}

/**
 * Get OSHA 300 Log (READ-ONLY)
 * Per 29 CFR 1904, these records must be maintained and cannot be modified.
 */
export async function getOsha300Log(year?: number): Promise<{
  entries: Osha300Entry[];
  statistics: OshaStatistics;
  year: number;
}> {
  const url = `/admin/osha/300${year ? `?year=${year}` : ''}`;
  const response = await get<Osha300Response>(url);
  return {
    entries: response.data.entries,
    statistics: response.data.statistics,
    year: response.data.year,
  };
}

/**
 * Get OSHA 300A Summary (READ-ONLY)
 * Per 29 CFR 1904, these records must be maintained and cannot be modified.
 */
export async function getOsha300ASummary(year?: number): Promise<{
  summary: Osha300AEntry | null;
  year: number;
}> {
  const url = `/admin/osha/300a${year ? `?year=${year}` : ''}`;
  const response = await get<Osha300AResponse>(url);
  return {
    summary: response.data.summary,
    year: response.data.year,
  };
}

// ============================================================================
// Export Default
// ============================================================================

export default {
  getAdminDashboard,
  getCaseStats,
  getRecentCases,
  getPatientFlowMetrics,
  getSitePerformance,
  getProviderPerformance,
  getStaffList,
  getClearanceStats,
  getComplianceAlerts,
  acknowledgeComplianceAlert,
  getTrainingModules,
  getExpiringCredentials,
  getOsha300Log,
  getOsha300ASummary,
};
