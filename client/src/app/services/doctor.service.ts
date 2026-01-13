/**
 * Doctor (MRO) Service
 *
 * Handles API calls for Doctor/MRO dashboard including DOT test verifications,
 * pending orders, verification history, and MRO-specific operations.
 * Part of the Doctor Dashboard workflow: View → API → ViewModel → Repository
 *
 * The Doctor role is associated with 'pclinician' role type (provider clinician)
 * and serves as the MRO interface for DOT drug testing, result verification,
 * and order signing.
 *
 * @module services/doctor
 */

import { get, post } from './api.js';

// ============================================================================
// Types
// ============================================================================

/**
 * Doctor/MRO statistics for the dashboard
 */
export interface DoctorStats {
  /** Number of DOT tests awaiting MRO verification */
  pendingVerifications: number;
  /** Number of orders awaiting doctor signature */
  ordersToSign: number;
  /** Number of tests reviewed today */
  reviewedToday: number;
  /** Average turnaround time in hours (collection to verification) */
  avgTurnaroundHours: number;
  /** Number of tests reviewed this month */
  testsReviewedThisMonth: number;
}

/**
 * DOT test pending MRO verification
 */
export interface PendingVerification {
  /** Test UUID */
  id: string;
  /** Patient's full name */
  patientName: string;
  /** Patient UUID */
  patientId: string;
  /** Type of test (e.g., "5-Panel Drug Screen") */
  testType: string;
  /** When specimen was collected (ISO 8601 format) */
  collectionDate: string;
  /** Current test status */
  status: 'pending' | 'positive' | 'negative' | 'cancelled' | 'invalid';
  /** Priority level based on status and wait time */
  priority: 'normal' | 'high' | 'urgent';
  /** Chain of custody verification status */
  chainOfCustodyStatus: 'not_started' | 'pending' | 'in_progress' | 'verified' | 'failed';
  /** Associated encounter UUID */
  encounterId: string;
  /** Specimen identifier */
  specimenId: string | null;
  /** Test modality */
  modality: 'drug_test' | 'alcohol_test';
}

/**
 * Order pending doctor signature
 */
export interface PendingOrder {
  /** Order ID */
  id: string;
  /** Patient's full name */
  patientName: string;
  /** Patient UUID */
  patientId: string | null;
  /** Type of order (e.g., "Lab Order", "Prescription") */
  orderType: string;
  /** Order description */
  description: string;
  /** When order was requested (ISO 8601 format) */
  requestDate: string;
  /** Order priority */
  priority: 'stat' | 'urgent' | 'routine';
  /** Associated encounter UUID */
  encounterId: string;
}

/**
 * Completed verification history entry
 */
export interface VerificationHistory {
  /** Test UUID */
  id: string;
  /** Patient's full name */
  patientName: string;
  /** Patient UUID */
  patientId: string;
  /** Type of test */
  testType: string;
  /** Verification result */
  result: string;
  /** When test was verified (ISO 8601 format) */
  verifiedAt: string;
}

/**
 * Detailed test information for MRO review
 */
export interface TestDetails {
  /** Test UUID */
  id: string;
  /** Associated encounter UUID */
  encounterId: string;
  /** Patient information */
  patient: {
    id: string;
    name: string;
    firstName: string;
    lastName: string;
    dob: string;
    phone: string | null;
    email: string | null;
  };
  /** Employer name at time of encounter */
  employer: string | null;
  /** Test information */
  testInfo: {
    modality: 'drug_test' | 'alcohol_test';
    type: string;
    specimenId: string | null;
    collectedAt: string;
    status: string;
  };
  /** Test results data */
  results: Record<string, unknown> | null;
  /** MRO review information */
  mroReview: {
    required: boolean;
    reviewedBy: string | null;
    reviewedAt: string | null;
  };
  /** Chain of custody information */
  chainOfCustody: {
    id: string | null;
    status: string;
    collectorSigned: boolean;
    donorSigned: boolean;
    createdAt: string | null;
  };
  /** Encounter information */
  encounter: {
    chiefComplaint: string | null;
    occurredOn: string | null;
  };
  /** When test was created */
  createdAt: string;
}

/**
 * Complete Doctor/MRO dashboard data
 */
export interface DoctorDashboardData {
  /** Doctor statistics */
  stats: DoctorStats;
  /** List of pending DOT verifications */
  pendingVerifications: PendingVerification[];
  /** List of pending orders */
  pendingOrders: PendingOrder[];
  /** Verification history */
  verificationHistory: VerificationHistory[];
}

/**
 * Verification result options
 */
export type VerificationResult = 
  | 'negative'
  | 'positive'
  | 'cancelled'
  | 'invalid'
  | 'dilute'
  | 'substituted'
  | 'adulterated'
  | 'refused';

// ============================================================================
// API Response Types
// ============================================================================

interface DashboardResponse {
  stats: DoctorStats;
  pendingVerifications: PendingVerification[];
  pendingOrders: PendingOrder[];
  verificationHistory: VerificationHistory[];
}

interface StatsResponse {
  stats: DoctorStats;
}

interface PendingVerificationsResponse {
  verifications: PendingVerification[];
  count: number;
}

interface VerificationHistoryResponse {
  history: VerificationHistory[];
  count: number;
}

interface TestDetailsResponse {
  test: TestDetails;
}

interface PendingOrdersResponse {
  orders: PendingOrder[];
  count: number;
}

interface VerifyTestResponse {
  message: string;
  testId: string;
  result: string;
}

interface SignOrderResponse {
  message: string;
  orderId: string;
}

// ============================================================================
// Service Functions
// ============================================================================

/**
 * Get complete Doctor/MRO dashboard data
 * 
 * Fetches doctor stats, pending verifications, pending orders, and
 * verification history in a single call.
 * 
 * @returns Promise resolving to complete dashboard data
 * 
 * @example
 * ```typescript
 * const dashboardData = await getDashboardData();
 * console.log(`Pending Verifications: ${dashboardData.stats.pendingVerifications}`);
 * console.log(`Verifications: ${dashboardData.pendingVerifications.length}`);
 * ```
 */
export async function getDashboardData(): Promise<DoctorDashboardData> {
  const response = await get<DashboardResponse>('/doctor/dashboard');
  return {
    stats: response.data.stats,
    pendingVerifications: response.data.pendingVerifications,
    pendingOrders: response.data.pendingOrders,
    verificationHistory: response.data.verificationHistory,
  };
}

/**
 * Get doctor statistics only
 * 
 * Fetches just the doctor counts for quick status updates or polling.
 * 
 * @returns Promise resolving to doctor statistics
 * 
 * @example
 * ```typescript
 * const stats = await getDoctorStats();
 * console.log(`${stats.pendingVerifications} verifications pending`);
 * ```
 */
export async function getDoctorStats(): Promise<DoctorStats> {
  const response = await get<StatsResponse>('/doctor/stats');
  return response.data.stats;
}

/**
 * Get pending DOT verifications list
 * 
 * Fetches DOT tests awaiting MRO verification.
 * 
 * @param limit - Maximum number of results (default: 20, max: 100)
 * @returns Promise resolving to array of pending verifications
 * 
 * @example
 * ```typescript
 * const verifications = await getPendingVerifications(10);
 * verifications.forEach(v => console.log(`${v.patientName} - ${v.testType}`));
 * ```
 */
export async function getPendingVerifications(limit?: number): Promise<PendingVerification[]> {
  const params = limit ? `?limit=${Math.min(Math.max(1, limit), 100)}` : '';
  const response = await get<PendingVerificationsResponse>(`/doctor/verifications/pending${params}`);
  return response.data.verifications;
}

/**
 * Get verification history list
 * 
 * Fetches recently verified tests.
 * 
 * @param limit - Maximum number of results (default: 20, max: 100)
 * @returns Promise resolving to array of verification history entries
 * 
 * @example
 * ```typescript
 * const history = await getVerificationHistory(5);
 * history.forEach(h => console.log(`${h.patientName} - ${h.result}`));
 * ```
 */
export async function getVerificationHistory(limit?: number): Promise<VerificationHistory[]> {
  const params = limit ? `?limit=${Math.min(Math.max(1, limit), 100)}` : '';
  const response = await get<VerificationHistoryResponse>(`/doctor/verifications/history${params}`);
  return response.data.history;
}

/**
 * Get detailed test information for MRO review
 * 
 * Fetches comprehensive test details including patient info, chain of custody,
 * and test results for MRO review.
 * 
 * @param testId - Test UUID
 * @returns Promise resolving to test details
 * 
 * @example
 * ```typescript
 * const details = await getTestDetails('abc-123-def');
 * console.log(`Test for ${details.patient.name}`);
 * console.log(`Chain of Custody: ${details.chainOfCustody.status}`);
 * ```
 */
export async function getTestDetails(testId: string): Promise<TestDetails> {
  const response = await get<TestDetailsResponse>(`/doctor/verifications/${testId}`);
  return response.data.test;
}

/**
 * Get pending orders list
 * 
 * Fetches orders awaiting doctor signature.
 * 
 * @param limit - Maximum number of results (default: 20, max: 100)
 * @returns Promise resolving to array of pending orders
 * 
 * @example
 * ```typescript
 * const orders = await getPendingOrders();
 * orders.forEach(o => console.log(`${o.patientName} - ${o.orderType}`));
 * ```
 */
export async function getPendingOrders(limit?: number): Promise<PendingOrder[]> {
  const params = limit ? `?limit=${Math.min(Math.max(1, limit), 100)}` : '';
  const response = await get<PendingOrdersResponse>(`/doctor/orders/pending${params}`);
  return response.data.orders;
}

/**
 * Submit MRO verification for a DOT test
 * 
 * Records the MRO's verification decision for a DOT drug test.
 * 
 * @param testId - Test UUID to verify
 * @param result - Verification result
 * @param comments - Optional MRO comments
 * @returns Promise resolving when verification is submitted
 * 
 * @example
 * ```typescript
 * await verifyTest('abc-123-def', 'negative', 'No issues identified.');
 * ```
 */
export async function verifyTest(
  testId: string,
  result: VerificationResult,
  comments?: string
): Promise<void> {
  await post<VerifyTestResponse>(`/doctor/verify/${testId}`, {
    result,
    comments: comments || null,
  });
}

/**
 * Sign an order
 * 
 * Records the doctor's signature on an order.
 * 
 * @param orderId - Order ID to sign
 * @returns Promise resolving when order is signed
 * 
 * @example
 * ```typescript
 * await signOrder('order-123');
 * ```
 */
export async function signOrder(orderId: string): Promise<void> {
  await post<SignOrderResponse>(`/doctor/sign/${orderId}`, {});
}

// ============================================================================
// Export Default
// ============================================================================

/**
 * Doctor service object containing all doctor/MRO-related methods
 */
export const doctorService = {
  getDashboardData,
  getDoctorStats,
  getPendingVerifications,
  getVerificationHistory,
  getTestDetails,
  getPendingOrders,
  verifyTest,
  signOrder,
} as const;

export default doctorService;
