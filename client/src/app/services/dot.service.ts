/**
 * DOT Testing Service for SafeShift EHR
 * 
 * Provides API methods for DOT (Department of Transportation) drug and alcohol testing.
 * Includes CCF management, result submission, and MRO verification.
 */

import { get, post, put, del } from './api.js';
import type { ApiResponse, PaginatedResponse } from '../types/api.types.js';
import type { DotTest, DotTestFilters, CreateDotTestDTO, DotTestResults, MroVerification } from '../types/api.types.js';

// ============================================================================
// DOT Test Functions
// ============================================================================

/**
 * Fetch paginated list of DOT tests
 * @param filters - Optional filters for the query
 * @returns Promise resolving to paginated DOT tests
 */
export async function getDotTests(filters?: DotTestFilters): Promise<ApiResponse<PaginatedResponse<DotTest>>> {
  return get<PaginatedResponse<DotTest>>('/dot-tests', {
    params: filters as Record<string, string | number | boolean | undefined>,
  });
}

/**
 * Fetch a single DOT test by ID
 * @param id - The DOT test ID
 * @returns Promise resolving to the DOT test
 */
export async function getDotTest(id: string): Promise<ApiResponse<DotTest>> {
  return get<DotTest>(`/dot-tests/${id}`);
}

/**
 * Initiate a new DOT test
 * @param data - DOT test creation data
 * @returns Promise resolving to the created DOT test
 */
export async function initiateDotTest(data: CreateDotTestDTO): Promise<ApiResponse<DotTest>> {
  return post<DotTest, CreateDotTestDTO>('/dot-tests', data);
}

/**
 * Update CCF (Custody and Control Form) data for a DOT test
 * @param id - The DOT test ID
 * @param ccfData - CCF form data
 * @returns Promise resolving to the updated DOT test
 */
export async function updateCcf(id: string, ccfData: Record<string, unknown>): Promise<ApiResponse<DotTest>> {
  return put<DotTest, Record<string, unknown>>(`/dot-tests/${id}/ccf`, ccfData);
}

/**
 * Submit test results from the lab
 * @param id - The DOT test ID
 * @param results - Test results data
 * @returns Promise resolving to the updated DOT test
 */
export async function submitDotResults(id: string, results: DotTestResults): Promise<ApiResponse<DotTest>> {
  return post<DotTest, DotTestResults>(`/dot-tests/${id}/results`, results);
}

/**
 * Submit MRO (Medical Review Officer) verification
 * @param id - The DOT test ID
 * @param verification - MRO verification data
 * @returns Promise resolving to the updated DOT test
 */
export async function mroVerify(id: string, verification: MroVerification): Promise<ApiResponse<DotTest>> {
  return post<DotTest, MroVerification>(`/dot-tests/${id}/mro-verify`, verification);
}

/**
 * Get DOT tests by status
 * @param status - The status to filter by
 * @returns Promise resolving to DOT tests array
 */
export async function getDotTestsByStatus(status: DotTest['status']): Promise<ApiResponse<DotTest[]>> {
  return get<DotTest[]>(`/dot-tests/status/${status}`);
}

/**
 * Cancel a DOT test
 * @param id - The DOT test ID
 * @param reason - Cancellation reason
 * @returns Promise resolving to the cancelled DOT test
 */
export async function cancelDotTest(id: string, reason: string): Promise<ApiResponse<DotTest>> {
  return post<DotTest, { reason: string }>(`/dot-tests/${id}/cancel`, { reason });
}

/**
 * Delete a DOT test (soft delete)
 * @param id - The DOT test ID
 * @returns Promise resolving to void
 */
export async function deleteDotTest(id: string): Promise<ApiResponse<void>> {
  return del<void>(`/dot-tests/${id}`);
}

/**
 * Get DOT test statistics
 * @returns Promise resolving to statistics
 */
export async function getDotTestStats(): Promise<ApiResponse<{
  pending: number;
  inProgress: number;
  completed: number;
  positive: number;
  negative: number;
}>> {
  return get('/dot-tests/stats');
}

// ============================================================================
// Service Object Export
// ============================================================================

/**
 * DOT testing service object with all DOT-related API methods
 */
export const dotService = {
  getTests: getDotTests,
  getTest: getDotTest,
  initiateTest: initiateDotTest,
  updateCcf,
  submitResults: submitDotResults,
  mroVerify,
  getByStatus: getDotTestsByStatus,
  cancel: cancelDotTest,
  delete: deleteDotTest,
  getStats: getDotTestStats,
};

export default dotService;
