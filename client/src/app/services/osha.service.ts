/**
 * OSHA Service for SafeShift EHR
 * 
 * Provides API methods for OSHA injury tracking, 300/300A log management,
 * incident rates calculation, and ITA submission.
 */

import { get, post, put, del } from './api.js';
import type { ApiResponse, PaginatedResponse } from '../types/api.types.js';
import type { 
  OshaInjury, 
  OshaLog, 
  OshaRates, 
  OshaFilters, 
  CreateInjuryDTO, 
  UpdateInjuryDTO 
} from '../types/api.types.js';

// ============================================================================
// Injury Functions
// ============================================================================

/**
 * Fetch paginated list of OSHA injuries
 * @param filters - Optional filters for the query
 * @returns Promise resolving to paginated injuries
 */
export async function getOshaInjuries(filters?: OshaFilters): Promise<ApiResponse<PaginatedResponse<OshaInjury>>> {
  return get<PaginatedResponse<OshaInjury>>('/osha/injuries', {
    params: filters as Record<string, string | number | boolean | undefined>,
  });
}

/**
 * Fetch a single OSHA injury by ID
 * @param id - The injury ID
 * @returns Promise resolving to the injury
 */
export async function getOshaInjury(id: string): Promise<ApiResponse<OshaInjury>> {
  return get<OshaInjury>(`/osha/injuries/${id}`);
}

/**
 * Record a new OSHA injury
 * @param data - Injury creation data
 * @returns Promise resolving to the created injury
 */
export async function recordOshaInjury(data: CreateInjuryDTO): Promise<ApiResponse<OshaInjury>> {
  return post<OshaInjury, CreateInjuryDTO>('/osha/injuries', data);
}

/**
 * Update an existing OSHA injury
 * @param id - The injury ID
 * @param data - Injury update data
 * @returns Promise resolving to the updated injury
 */
export async function updateOshaInjury(id: string, data: UpdateInjuryDTO): Promise<ApiResponse<OshaInjury>> {
  return put<OshaInjury, UpdateInjuryDTO>(`/osha/injuries/${id}`, data);
}

/**
 * Delete an OSHA injury (soft delete)
 * @param id - The injury ID
 * @returns Promise resolving to void
 */
export async function deleteOshaInjury(id: string): Promise<ApiResponse<void>> {
  return del<void>(`/osha/injuries/${id}`);
}

// ============================================================================
// OSHA Log Functions
// ============================================================================

/**
 * Get OSHA Form 300 log
 * @param year - The year for the log
 * @param establishmentId - Optional establishment ID
 * @returns Promise resolving to the 300 log
 */
export async function get300Log(year: number, establishmentId?: string): Promise<ApiResponse<OshaLog>> {
  return get<OshaLog>('/osha/300-log', {
    params: { year, establishment_id: establishmentId },
  });
}

/**
 * Get OSHA Form 300A summary log
 * @param year - The year for the log
 * @param establishmentId - Optional establishment ID
 * @returns Promise resolving to the 300A log
 */
export async function get300ALog(year: number, establishmentId?: string): Promise<ApiResponse<OshaLog>> {
  return get<OshaLog>('/osha/300a-log', {
    params: { year, establishment_id: establishmentId },
  });
}

/**
 * Get OSHA incident rates (TRIR, DART, LTIR)
 * @param year - The year for calculations
 * @param establishmentId - Optional establishment ID
 * @returns Promise resolving to the rates
 */
export async function getOshaRates(year: number, establishmentId?: string): Promise<ApiResponse<OshaRates>> {
  return get<OshaRates>('/osha/rates', {
    params: { year, establishment_id: establishmentId },
  });
}

// ============================================================================
// ITA Submission Functions
// ============================================================================

/**
 * Submit data to OSHA ITA (Injury Tracking Application)
 * @param data - ITA submission data
 * @returns Promise resolving to submission result
 */
export async function submitToIta(data: {
  year: number;
  establishmentId: string;
  submissionType: 'form-300a' | 'form-300' | 'both';
}): Promise<ApiResponse<{ submissionId: string; status: string }>> {
  return post('/osha/submit-ita', data);
}

/**
 * Get ITA submission history
 * @returns Promise resolving to submission history
 */
export async function getItaSubmissions(): Promise<ApiResponse<Array<{
  id: string;
  year: number;
  submittedAt: string;
  status: string;
}>>> {
  return get('/osha/ita-submissions');
}

// ============================================================================
// Service Object Export
// ============================================================================

/**
 * OSHA service object with all OSHA-related API methods
 */
export const oshaService = {
  getInjuries: getOshaInjuries,
  getInjury: getOshaInjury,
  recordInjury: recordOshaInjury,
  updateInjury: updateOshaInjury,
  deleteInjury: deleteOshaInjury,
  get300Log,
  get300ALog,
  getRates: getOshaRates,
  submitToIta,
  getItaSubmissions,
};

export default oshaService;
