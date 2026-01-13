/**
 * Clinical Provider Service
 *
 * Handles API calls for clinical provider dashboard including provider statistics,
 * active encounters, recent encounters, pending orders, and QA reviews.
 * Part of the Clinical Provider Dashboard workflow: View → API → ViewModel → Repository
 *
 * @module services/clinicalprovider
 */

import { get } from './api.js';

// ============================================================================
// Types
// ============================================================================

/**
 * Provider statistics for the clinical provider dashboard
 */
export interface ProviderStats {
  /** Number of active encounters assigned to provider */
  activeEncounters: number;
  /** Number of orders pending signature/review */
  pendingOrders: number;
  /** Number of encounters completed today */
  completedToday: number;
  /** Number of QA reviews pending */
  pendingQAReviews: number;
}

/**
 * Active encounter information
 */
export interface ActiveEncounter {
  /** Encounter UUID */
  id: string;
  /** Patient's full name */
  patientName: string;
  /** Chief complaint */
  chiefComplaint: string | null;
  /** Current status */
  status: 'arrived' | 'in_progress';
  /** When encounter started (ISO 8601 format) */
  startTime: string;
  /** Priority level based on wait time */
  priority: 'normal' | 'high' | 'urgent';
  /** Type of encounter */
  encounterType?: string;
  /** Patient date of birth */
  patientDob?: string;
}

/**
 * Recent encounter information for follow-up
 */
export interface RecentEncounter {
  /** Encounter UUID */
  id: string;
  /** Patient's full name */
  patient: string;
  /** Patient data for follow-up navigation */
  patientData: {
    firstName: string;
    lastName: string;
    dob: string;
    patientId: string;
  };
  /** Encounter type description */
  type: string;
  /** Last seen date (formatted for display) */
  lastSeen: string;
  /** Clearance status */
  clearanceStatus: 'Cleared' | 'Not Cleared' | 'Pending';
  /** Whether appointment reminder was sent */
  reminderSent: boolean;
}

/**
 * Pending order information
 */
export interface PendingOrder {
  /** Order UUID */
  id: string;
  /** Patient's full name */
  patientName: string;
  /** Type of order */
  orderType: string;
  /** Order priority */
  priority: 'stat' | 'urgent' | 'routine';
  /** When order was created (ISO 8601 format) */
  createdAt: string;
  /** Associated encounter UUID */
  encounterId: string;
}

/**
 * QA Review item
 */
export interface QAReview {
  /** Review UUID */
  id: string;
  /** Associated encounter UUID */
  encounterId: string;
  /** Patient's full name */
  patientName: string;
  /** Chief complaint from encounter */
  chiefComplaint: string | null;
  /** Review status */
  status: 'pending' | 'approved' | 'rejected' | 'flagged';
  /** Review notes */
  notes: string | null;
  /** When review was created (ISO 8601 format) */
  createdAt: string;
}

/**
 * Complete clinical provider dashboard data
 */
export interface ClinicalProviderDashboardData {
  /** Provider statistics */
  stats: ProviderStats;
  /** List of active encounters */
  activeEncounters: ActiveEncounter[];
  /** List of recent encounters */
  recentEncounters: RecentEncounter[];
  /** List of pending orders */
  pendingOrders: PendingOrder[];
}

// ============================================================================
// API Response Types
// ============================================================================

interface DashboardResponse {
  stats: ProviderStats;
  activeEncounters: ActiveEncounter[];
  recentEncounters: RecentEncounter[];
  pendingOrders: PendingOrder[];
}

interface StatsResponse {
  stats: ProviderStats;
}

interface ActiveEncountersResponse {
  activeEncounters: ActiveEncounter[];
  count: number;
}

interface RecentEncountersResponse {
  recentEncounters: RecentEncounter[];
  count: number;
}

interface PendingOrdersResponse {
  pendingOrders: PendingOrder[];
  count: number;
}

interface QAReviewsResponse {
  qaReviews: QAReview[];
  count: number;
}

// ============================================================================
// Service Functions
// ============================================================================

/**
 * Get complete clinical provider dashboard data
 * 
 * Fetches provider stats, active encounters, recent encounters, and pending orders
 * in a single call.
 * 
 * @returns Promise resolving to complete dashboard data
 * 
 * @example
 * ```typescript
 * const dashboardData = await getDashboardData();
 * console.log(`Active Encounters: ${dashboardData.stats.activeEncounters}`);
 * console.log(`Encounters: ${dashboardData.activeEncounters.length}`);
 * ```
 */
export async function getDashboardData(): Promise<ClinicalProviderDashboardData> {
  const response = await get<DashboardResponse>('/clinicalprovider/dashboard');
  return {
    stats: response.data.stats,
    activeEncounters: response.data.activeEncounters,
    recentEncounters: response.data.recentEncounters,
    pendingOrders: response.data.pendingOrders,
  };
}

/**
 * Get provider statistics only
 * 
 * Fetches just the provider counts for quick status updates or polling.
 * 
 * @returns Promise resolving to provider statistics
 * 
 * @example
 * ```typescript
 * const stats = await getStats();
 * console.log(`${stats.activeEncounters} active encounters`);
 * ```
 */
export async function getStats(): Promise<ProviderStats> {
  const response = await get<StatsResponse>('/clinicalprovider/stats');
  return response.data.stats;
}

/**
 * Get active encounters list
 * 
 * Fetches the list of encounters in progress or awaiting provider.
 * 
 * @param limit - Maximum number of results (default: 10, max: 50)
 * @returns Promise resolving to array of active encounters
 * 
 * @example
 * ```typescript
 * const encounters = await getActiveEncounters(10);
 * encounters.forEach(e => console.log(`${e.patientName} - ${e.chiefComplaint}`));
 * ```
 */
export async function getActiveEncounters(limit?: number): Promise<ActiveEncounter[]> {
  const params = limit ? `?limit=${Math.min(Math.max(1, limit), 50)}` : '';
  const response = await get<ActiveEncountersResponse>(`/clinicalprovider/encounters/active${params}`);
  return response.data.activeEncounters;
}

/**
 * Get recent encounters list
 * 
 * Fetches recently completed encounters for follow-up purposes.
 * 
 * @param limit - Maximum number of results (default: 10, max: 50)
 * @returns Promise resolving to array of recent encounters
 * 
 * @example
 * ```typescript
 * const recent = await getRecentEncounters(5);
 * recent.forEach(e => console.log(`${e.patient} - ${e.lastSeen}`));
 * ```
 */
export async function getRecentEncounters(limit?: number): Promise<RecentEncounter[]> {
  const params = limit ? `?limit=${Math.min(Math.max(1, limit), 50)}` : '';
  const response = await get<RecentEncountersResponse>(`/clinicalprovider/encounters/recent${params}`);
  return response.data.recentEncounters;
}

/**
 * Get pending orders list
 * 
 * Fetches orders awaiting provider signature or review.
 * 
 * @param limit - Maximum number of results (default: 10, max: 50)
 * @returns Promise resolving to array of pending orders
 * 
 * @example
 * ```typescript
 * const orders = await getPendingOrders();
 * orders.forEach(o => console.log(`${o.patientName} - ${o.orderType}`));
 * ```
 */
export async function getPendingOrders(limit?: number): Promise<PendingOrder[]> {
  const params = limit ? `?limit=${Math.min(Math.max(1, limit), 50)}` : '';
  const response = await get<PendingOrdersResponse>(`/clinicalprovider/orders/pending${params}`);
  return response.data.pendingOrders;
}

/**
 * Get pending QA reviews list
 * 
 * Fetches QA review items assigned to or available for the provider.
 * 
 * @param limit - Maximum number of results (default: 10, max: 50)
 * @returns Promise resolving to array of QA reviews
 * 
 * @example
 * ```typescript
 * const reviews = await getPendingQAReviews();
 * reviews.forEach(r => console.log(`${r.patientName} - ${r.status}`));
 * ```
 */
export async function getPendingQAReviews(limit?: number): Promise<QAReview[]> {
  const params = limit ? `?limit=${Math.min(Math.max(1, limit), 50)}` : '';
  const response = await get<QAReviewsResponse>(`/clinicalprovider/qa/pending${params}`);
  return response.data.qaReviews;
}

// ============================================================================
// Export Default
// ============================================================================

/**
 * Clinical Provider service object containing all clinical provider-related methods
 */
export const clinicalProviderService = {
  getDashboardData,
  getStats,
  getActiveEncounters,
  getRecentEncounters,
  getPendingOrders,
  getPendingQAReviews,
} as const;

export default clinicalProviderService;
