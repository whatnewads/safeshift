/**
 * Registration Service
 *
 * Handles API calls for registration dashboard including queue statistics,
 * pending registrations, and patient search functionality.
 * Part of the Registration Dashboard workflow: View → API → ViewModel → Repository
 *
 * @module services/registration
 */

import { get } from './api.js';

// ============================================================================
// Types
// ============================================================================

/**
 * Queue statistics for the registration dashboard
 */
export interface QueueStats {
  /** Number of patients waiting to be registered */
  waiting: number;
  /** Number of registrations currently in progress */
  inProgress: number;
  /** Number of registrations completed today */
  completedToday: number;
}

/**
 * Patient information for pending registration list
 */
export interface PendingRegistration {
  /** Patient UUID */
  id: string;
  /** Patient's first name */
  firstName: string;
  /** Patient's last name */
  lastName: string;
  /** Date of birth (YYYY-MM-DD format) */
  dateOfBirth: string;
  /** Human-readable wait time (e.g., "12 min", "1 hr 5 min") */
  waitTime: string;
  /** Priority level based on wait time */
  priority: 'normal' | 'high' | 'urgent';
  /** Associated encounter UUID */
  encounterId?: string;
  /** Patient phone number */
  phone?: string | null;
  /** Chief complaint for the visit */
  chiefComplaint?: string | null;
}

/**
 * Patient search result
 */
export interface PatientSearchResult {
  /** Patient UUID */
  id: string;
  /** Patient's first name */
  firstName: string;
  /** Patient's last name */
  lastName: string;
  /** Preferred name (nickname) */
  preferredName?: string | null;
  /** Date of birth (YYYY-MM-DD format) */
  dateOfBirth: string;
  /** Phone number */
  phone?: string | null;
  /** Email address */
  email?: string | null;
}

/**
 * Complete registration dashboard data
 */
export interface RegistrationDashboardData {
  /** Queue statistics */
  queueStats: QueueStats;
  /** List of pending registrations */
  pendingRegistrations: PendingRegistration[];
}

// ============================================================================
// API Response Types
// ============================================================================

interface DashboardResponse {
  queueStats: QueueStats;
  pendingRegistrations: PendingRegistration[];
}

interface QueueStatsResponse {
  queueStats: QueueStats;
}

interface PendingRegistrationsResponse {
  pendingRegistrations: PendingRegistration[];
  count: number;
}

interface PatientSearchResponse {
  patients: PatientSearchResult[];
  count: number;
  query: string;
}

interface PatientResponse {
  patient: PatientSearchResult;
}

// ============================================================================
// Service Functions
// ============================================================================

/**
 * Get complete registration dashboard data
 * 
 * Fetches queue statistics and pending registrations in a single call.
 * 
 * @returns Promise resolving to complete dashboard data
 * 
 * @example
 * ```typescript
 * const dashboardData = await getDashboardData();
 * console.log(`Waiting: ${dashboardData.queueStats.waiting}`);
 * console.log(`Pending registrations: ${dashboardData.pendingRegistrations.length}`);
 * ```
 */
export async function getDashboardData(): Promise<RegistrationDashboardData> {
  const response = await get<DashboardResponse>('/registration/dashboard');
  return {
    queueStats: response.data.queueStats,
    pendingRegistrations: response.data.pendingRegistrations,
  };
}

/**
 * Get queue statistics only
 * 
 * Fetches just the queue counts for quick status updates or polling.
 * 
 * @returns Promise resolving to queue statistics
 * 
 * @example
 * ```typescript
 * const stats = await getQueueStats();
 * console.log(`${stats.waiting} patients waiting`);
 * ```
 */
export async function getQueueStats(): Promise<QueueStats> {
  const response = await get<QueueStatsResponse>('/registration/queue');
  return response.data.queueStats;
}

/**
 * Get pending registrations list
 * 
 * Fetches the list of patients waiting for registration.
 * 
 * @param limit - Maximum number of results (default: 20, max: 100)
 * @returns Promise resolving to array of pending registrations
 * 
 * @example
 * ```typescript
 * const pending = await getPendingRegistrations(10);
 * pending.forEach(p => console.log(`${p.firstName} ${p.lastName} - ${p.waitTime}`));
 * ```
 */
export async function getPendingRegistrations(limit?: number): Promise<PendingRegistration[]> {
  const params = limit ? `?limit=${Math.min(Math.max(1, limit), 100)}` : '';
  const response = await get<PendingRegistrationsResponse>(`/registration/pending${params}`);
  return response.data.pendingRegistrations;
}

/**
 * Search for patients
 * 
 * Search patients by name, phone, email, or date of birth.
 * Useful for patient lookup during registration workflows.
 * 
 * @param query - Search query (minimum 2 characters)
 * @returns Promise resolving to array of matching patients
 * @throws Error if query is less than 2 characters
 * 
 * @example
 * ```typescript
 * // Search by name
 * const results = await searchPatients('John');
 * 
 * // Search by phone
 * const byPhone = await searchPatients('555-1234');
 * ```
 */
export async function searchPatients(query: string): Promise<PatientSearchResult[]> {
  const trimmedQuery = query.trim();
  
  if (trimmedQuery.length < 2) {
    throw new Error('Search query must be at least 2 characters');
  }
  
  const response = await get<PatientSearchResponse>('/registration/search', {
    params: { q: trimmedQuery },
  });
  return response.data.patients;
}

/**
 * Get a single patient by ID
 * 
 * Retrieve detailed patient information for registration.
 * 
 * @param patientId - Patient UUID
 * @returns Promise resolving to patient data
 * @throws Error if patient not found
 * 
 * @example
 * ```typescript
 * const patient = await getPatient('uuid-here');
 * console.log(`${patient.firstName} ${patient.lastName}`);
 * ```
 */
export async function getPatient(patientId: string): Promise<PatientSearchResult> {
  const response = await get<PatientResponse>(`/registration/patient/${patientId}`);
  return response.data.patient;
}

// ============================================================================
// Export Default
// ============================================================================

/**
 * Registration service object containing all registration-related methods
 */
export const registrationService = {
  getDashboardData,
  getQueueStats,
  getPendingRegistrations,
  searchPatients,
  getPatient,
} as const;

export default registrationService;
