/**
 * Privacy Officer Service
 *
 * Handles API calls for privacy officer dashboard including compliance KPIs,
 * PHI access logs, consent status, regulatory updates, and training compliance.
 * Part of the Privacy Officer Dashboard workflow: View → API → ViewModel → Repository
 *
 * @module services/privacy
 */

import { get } from './api.js';

// ============================================================================
// Types
// ============================================================================

/**
 * Compliance KPIs for the privacy officer dashboard
 */
export interface ComplianceKPIs {
  /** Percentage of staff with current HIPAA training */
  trainingCompletion: number;
  /** Percentage of patients with valid consents */
  consentCompliance: number;
  /** Number of breach incidents in current year */
  breachCount: number;
  /** Overall compliance score (weighted average) */
  overallScore: number;
}

/**
 * PHI Access Log entry
 */
export interface AccessLog {
  /** Log entry UUID */
  id: string;
  /** Name of the user who accessed PHI */
  accessorName: string;
  /** Role of the accessor */
  accessorRole: string;
  /** Patient ID if applicable */
  patientId: string | null;
  /** Type of access (view, insert, update, delete, export, print) */
  accessType: string;
  /** Reason for access */
  reason: string;
  /** Table that was accessed */
  tableName: string;
  /** Access timestamp (ISO 8601 format) */
  timestamp: string;
}

/**
 * Patient consent status
 */
export interface ConsentStatus {
  /** Consent record ID */
  consentId: string;
  /** Patient UUID */
  patientId: string;
  /** Patient's full name */
  patientName: string;
  /** Type of consent (treatment, research, etc.) */
  consentType: string;
  /** Consent status (granted, declined, pending, expired) */
  status: 'granted' | 'declined' | 'pending' | 'expired';
  /** Last update timestamp (ISO 8601 format) */
  lastUpdated: string;
  /** Expiration date if applicable (ISO 8601 format) */
  expiresAt: string | null;
}

/**
 * Regulatory update information
 */
export interface RegulatoryUpdate {
  /** Update UUID */
  id: string;
  /** Update title */
  title: string;
  /** Brief summary */
  summary: string;
  /** Source of the update (HIPAA, OSHA, DOT, etc.) */
  source: string;
  /** Effective date */
  effectiveDate: string;
  /** Priority level */
  priority: 'critical' | 'high' | 'medium' | 'low';
  /** Status of the update (pending, reviewed, implemented, archived) */
  status: string;
  /** When the update was created (ISO 8601 format) */
  createdAt: string;
}

/**
 * Breach incident information
 */
export interface BreachIncident {
  /** Incident UUID */
  id: string;
  /** Incident title */
  title: string;
  /** Incident description */
  description: string;
  /** Severity level */
  severity: 'critical' | 'high' | 'medium' | 'low';
  /** Current status */
  status: 'open' | 'investigating' | 'resolved' | 'closed';
  /** When reported (ISO 8601 format) */
  reportedAt: string;
  /** When acknowledged (ISO 8601 format) */
  acknowledgedAt: string | null;
}

/**
 * Training compliance statistics
 */
export interface TrainingStats {
  /** Total training records */
  total: number;
  /** Number of compliant records */
  compliant: number;
  /** Number expiring within 30 days */
  expiringSoon: number;
  /** Number of overdue records */
  overdue: number;
}

/**
 * Training record information
 */
export interface TrainingRecord {
  /** Record UUID */
  recordId: string;
  /** User UUID */
  userId: string;
  /** Username */
  username: string;
  /** Training title */
  trainingTitle: string;
  /** Completion date (ISO 8601 format) */
  completedAt: string | null;
  /** Expiration date (ISO 8601 format) */
  expiresAt: string | null;
  /** Training status */
  status: string;
  /** Compliance status */
  complianceStatus: 'compliant' | 'expiring_soon' | 'overdue';
}

/**
 * Training compliance data
 */
export interface TrainingCompliance {
  /** Training statistics */
  stats: TrainingStats;
  /** Training records */
  records: TrainingRecord[];
}

/**
 * Complete privacy officer dashboard data
 */
export interface PrivacyOfficerDashboardData {
  /** Compliance KPIs */
  complianceKPIs: ComplianceKPIs;
  /** PHI access logs */
  phiAccessLogs: AccessLog[];
  /** Consent status records */
  consentStatus: ConsentStatus[];
  /** Regulatory updates */
  regulatoryUpdates: RegulatoryUpdate[];
  /** Training compliance data */
  trainingCompliance: TrainingCompliance;
}

// ============================================================================
// API Response Types
// ============================================================================

interface DashboardResponse {
  complianceKPIs: ComplianceKPIs;
  phiAccessLogs: AccessLog[];
  consentStatus: ConsentStatus[];
  regulatoryUpdates: RegulatoryUpdate[];
  trainingCompliance: TrainingCompliance;
}

interface KPIsResponse {
  complianceKPIs: ComplianceKPIs;
}

interface AccessLogsResponse {
  phiAccessLogs: AccessLog[];
  count: number;
}

interface ConsentStatusResponse {
  consentStatus: ConsentStatus[];
  count: number;
}

interface RegulatoryUpdatesResponse {
  regulatoryUpdates: RegulatoryUpdate[];
  count: number;
}

interface BreachIncidentsResponse {
  breachIncidents: BreachIncident[];
  count: number;
}

interface TrainingComplianceResponse {
  trainingStats: TrainingStats;
  trainingRecords: TrainingRecord[];
  count: number;
}

// ============================================================================
// Service Functions
// ============================================================================

/**
 * Get complete privacy officer dashboard data
 * 
 * Fetches compliance KPIs, PHI access logs, consent status, regulatory updates,
 * and training compliance in a single call.
 * 
 * @returns Promise resolving to complete dashboard data
 * 
 * @example
 * ```typescript
 * const dashboardData = await getDashboardData();
 * console.log(`Compliance Score: ${dashboardData.complianceKPIs.overallScore}%`);
 * console.log(`PHI Access Logs: ${dashboardData.phiAccessLogs.length}`);
 * ```
 */
export async function getDashboardData(): Promise<PrivacyOfficerDashboardData> {
  const response = await get<DashboardResponse>('/privacy/dashboard');
  return {
    complianceKPIs: response.data.complianceKPIs,
    phiAccessLogs: response.data.phiAccessLogs,
    consentStatus: response.data.consentStatus,
    regulatoryUpdates: response.data.regulatoryUpdates,
    trainingCompliance: response.data.trainingCompliance,
  };
}

/**
 * Get compliance KPIs only
 * 
 * Fetches just the compliance metrics for quick status updates or polling.
 * 
 * @returns Promise resolving to compliance KPIs
 * 
 * @example
 * ```typescript
 * const kpis = await getComplianceKPIs();
 * console.log(`Training Completion: ${kpis.trainingCompletion}%`);
 * ```
 */
export async function getComplianceKPIs(): Promise<ComplianceKPIs> {
  const response = await get<KPIsResponse>('/privacy/compliance/kpis');
  return response.data.complianceKPIs;
}

/**
 * Get PHI access logs
 * 
 * Fetches recent PHI access events from the audit log.
 * 
 * @param limit - Maximum number of results (default: 20, max: 100)
 * @returns Promise resolving to array of access logs
 * 
 * @example
 * ```typescript
 * const logs = await getPHIAccessLogs(50);
 * logs.forEach(log => console.log(`${log.accessorName} - ${log.accessType}`));
 * ```
 */
export async function getPHIAccessLogs(limit?: number): Promise<AccessLog[]> {
  const params = limit ? `?limit=${Math.min(Math.max(1, limit), 100)}` : '';
  const response = await get<AccessLogsResponse>(`/privacy/access-logs${params}`);
  return response.data.phiAccessLogs;
}

/**
 * Get consent status overview
 * 
 * Fetches patient consent records with their current status.
 * 
 * @param limit - Maximum number of results (default: 20, max: 100)
 * @returns Promise resolving to array of consent status records
 * 
 * @example
 * ```typescript
 * const consents = await getConsentStatus();
 * const pending = consents.filter(c => c.status === 'pending');
 * console.log(`Pending consents: ${pending.length}`);
 * ```
 */
export async function getConsentStatus(limit?: number): Promise<ConsentStatus[]> {
  const params = limit ? `?limit=${Math.min(Math.max(1, limit), 100)}` : '';
  const response = await get<ConsentStatusResponse>(`/privacy/consents${params}`);
  return response.data.consentStatus;
}

/**
 * Get regulatory updates
 * 
 * Fetches pending HIPAA/regulatory updates that need attention.
 * 
 * @param limit - Maximum number of results (default: 10, max: 50)
 * @returns Promise resolving to array of regulatory updates
 * 
 * @example
 * ```typescript
 * const updates = await getRegulatoryUpdates();
 * const highPriority = updates.filter(u => u.priority === 'high' || u.priority === 'critical');
 * console.log(`High priority updates: ${highPriority.length}`);
 * ```
 */
export async function getRegulatoryUpdates(limit?: number): Promise<RegulatoryUpdate[]> {
  const params = limit ? `?limit=${Math.min(Math.max(1, limit), 50)}` : '';
  const response = await get<RegulatoryUpdatesResponse>(`/privacy/regulatory${params}`);
  return response.data.regulatoryUpdates;
}

/**
 * Get breach incidents
 * 
 * Fetches security breach incident records.
 * 
 * @param limit - Maximum number of results (default: 10, max: 50)
 * @returns Promise resolving to array of breach incidents
 * 
 * @example
 * ```typescript
 * const incidents = await getBreachIncidents();
 * const open = incidents.filter(i => i.status === 'open');
 * console.log(`Open incidents: ${open.length}`);
 * ```
 */
export async function getBreachIncidents(limit?: number): Promise<BreachIncident[]> {
  const params = limit ? `?limit=${Math.min(Math.max(1, limit), 50)}` : '';
  const response = await get<BreachIncidentsResponse>(`/privacy/breaches${params}`);
  return response.data.breachIncidents;
}

/**
 * Get training compliance data
 * 
 * Fetches staff HIPAA training compliance status and records.
 * 
 * @param limit - Maximum number of records (default: 20, max: 100)
 * @returns Promise resolving to training compliance data
 * 
 * @example
 * ```typescript
 * const training = await getTrainingCompliance();
 * console.log(`Compliant: ${training.stats.compliant}/${training.stats.total}`);
 * ```
 */
export async function getTrainingCompliance(limit?: number): Promise<TrainingCompliance> {
  const params = limit ? `?limit=${Math.min(Math.max(1, limit), 100)}` : '';
  const response = await get<TrainingComplianceResponse>(`/privacy/training${params}`);
  return {
    stats: response.data.trainingStats,
    records: response.data.trainingRecords,
  };
}

// ============================================================================
// Export Default
// ============================================================================

/**
 * Privacy Officer service object containing all privacy officer-related methods
 */
export const privacyService = {
  getDashboardData,
  getComplianceKPIs,
  getPHIAccessLogs,
  getConsentStatus,
  getRegulatoryUpdates,
  getBreachIncidents,
  getTrainingCompliance,
} as const;

export default privacyService;
