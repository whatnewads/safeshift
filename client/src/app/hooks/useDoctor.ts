/**
 * Doctor (MRO) Dashboard Hook
 *
 * React hooks for Doctor/MRO dashboard functionality including DOT test
 * verifications, pending orders, verification history, and MRO-specific operations.
 *
 * Part of the Doctor Dashboard workflow: View → Hook → Service → API
 *
 * The Doctor role is associated with 'pclinician' role type (provider clinician)
 * and serves as the MRO interface for DOT drug testing, result verification,
 * and order signing.
 *
 * @module hooks/useDoctor
 */

import { useState, useEffect, useCallback } from 'react';
import {
  getDashboardData,
  getDoctorStats,
  getPendingVerifications,
  getVerificationHistory,
  getTestDetails,
  getPendingOrders,
  verifyTest,
  signOrder,
} from '../services/doctor.service.js';
import type {
  DoctorStats,
  PendingVerification,
  PendingOrder,
  VerificationHistory,
  TestDetails,
  VerificationResult,
} from '../services/doctor.service.js';

// ============================================================================
// Types
// ============================================================================

/**
 * Return type for the main doctor dashboard hook
 */
interface UseDoctorReturn {
  /** Doctor statistics */
  stats: DoctorStats | null;
  /** List of pending DOT verifications */
  pendingVerifications: PendingVerification[];
  /** List of pending orders */
  pendingOrders: PendingOrder[];
  /** Verification history */
  verificationHistory: VerificationHistory[];
  /** Loading state */
  loading: boolean;
  /** Error message if any */
  error: string | null;
  /** Refetch dashboard data */
  refetch: () => Promise<void>;
  /** Clear any errors */
  clearError: () => void;
  /** Submit MRO verification for a test */
  submitVerification: (testId: string, result: VerificationResult, comments?: string) => Promise<void>;
  /** Sign an order */
  submitSignOrder: (orderId: string) => Promise<void>;
}

/**
 * Return type for the doctor stats only hook
 */
interface UseDoctorStatsReturn {
  /** Doctor statistics */
  stats: DoctorStats | null;
  /** Loading state */
  loading: boolean;
  /** Error message if any */
  error: string | null;
  /** Refetch stats */
  refetch: () => Promise<void>;
}

/**
 * Return type for the pending verifications hook
 */
interface UsePendingVerificationsReturn {
  /** List of pending verifications */
  pendingVerifications: PendingVerification[];
  /** Loading state */
  loading: boolean;
  /** Error message if any */
  error: string | null;
  /** Refetch verifications */
  refetch: () => Promise<void>;
  /** Set the limit for results */
  setLimit: (limit: number) => void;
  /** Submit MRO verification */
  verify: (testId: string, result: VerificationResult, comments?: string) => Promise<void>;
}

/**
 * Return type for the verification history hook
 */
interface UseVerificationHistoryReturn {
  /** List of verification history entries */
  history: VerificationHistory[];
  /** Loading state */
  loading: boolean;
  /** Error message if any */
  error: string | null;
  /** Refetch history */
  refetch: () => Promise<void>;
  /** Set the limit for results */
  setLimit: (limit: number) => void;
}

/**
 * Return type for the test details hook
 */
interface UseTestDetailsReturn {
  /** Test details */
  test: TestDetails | null;
  /** Loading state */
  loading: boolean;
  /** Error message if any */
  error: string | null;
  /** Refetch test details */
  refetch: () => Promise<void>;
  /** Submit MRO verification for this test */
  verify: (result: VerificationResult, comments?: string) => Promise<void>;
}

/**
 * Return type for the pending orders hook
 */
interface UseDoctorPendingOrdersReturn {
  /** List of pending orders */
  pendingOrders: PendingOrder[];
  /** Loading state */
  loading: boolean;
  /** Error message if any */
  error: string | null;
  /** Refetch orders */
  refetch: () => Promise<void>;
  /** Set the limit for results */
  setLimit: (limit: number) => void;
  /** Sign an order */
  sign: (orderId: string) => Promise<void>;
}

// ============================================================================
// useDoctor Hook (Main Dashboard Hook)
// ============================================================================

/**
 * Main hook for Doctor/MRO dashboard data
 * 
 * Fetches complete dashboard data on mount and provides refetch capability.
 * Also provides methods for verifying tests and signing orders.
 * 
 * @returns Doctor dashboard state and operations
 * 
 * @example
 * ```typescript
 * function DoctorDashboard() {
 *   const { stats, pendingVerifications, loading, error, submitVerification, refetch } = useDoctor();
 *   
 *   if (loading) return <LoadingSpinner />;
 *   if (error) return <ErrorMessage message={error} />;
 *   
 *   const handleVerify = async (testId: string) => {
 *     await submitVerification(testId, 'negative', 'No issues identified.');
 *     await refetch();
 *   };
 *   
 *   return (
 *     <div>
 *       <StatsDisplay stats={stats} />
 *       <VerificationQueue verifications={pendingVerifications} onVerify={handleVerify} />
 *     </div>
 *   );
 * }
 * ```
 */
export function useDoctor(): UseDoctorReturn {
  const [stats, setStats] = useState<DoctorStats | null>(null);
  const [pendingVerifications, setPendingVerifications] = useState<PendingVerification[]>([]);
  const [pendingOrders, setPendingOrders] = useState<PendingOrder[]>([]);
  const [verificationHistory, setVerificationHistory] = useState<VerificationHistory[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const fetchDashboard = useCallback(async () => {
    try {
      setLoading(true);
      setError(null);
      const data = await getDashboardData();
      setStats(data.stats);
      setPendingVerifications(data.pendingVerifications);
      setPendingOrders(data.pendingOrders);
      setVerificationHistory(data.verificationHistory);
    } catch (err) {
      const message = err instanceof Error ? err.message : 'Failed to load doctor dashboard';
      setError(message);
    } finally {
      setLoading(false);
    }
  }, []);

  const clearError = useCallback(() => {
    setError(null);
  }, []);

  const submitVerification = useCallback(async (
    testId: string,
    result: VerificationResult,
    comments?: string
  ) => {
    try {
      await verifyTest(testId, result, comments);
      // Remove the verified test from pending list
      setPendingVerifications(prev => prev.filter(v => v.id !== testId));
      // Update stats
      if (stats) {
        setStats({
          ...stats,
          pendingVerifications: Math.max(0, stats.pendingVerifications - 1),
          reviewedToday: stats.reviewedToday + 1,
        });
      }
    } catch (err) {
      const message = err instanceof Error ? err.message : 'Failed to submit verification';
      throw new Error(message);
    }
  }, [stats]);

  const submitSignOrder = useCallback(async (orderId: string) => {
    try {
      await signOrder(orderId);
      // Remove the signed order from pending list
      setPendingOrders(prev => prev.filter(o => o.id !== orderId));
      // Update stats
      if (stats) {
        setStats({
          ...stats,
          ordersToSign: Math.max(0, stats.ordersToSign - 1),
        });
      }
    } catch (err) {
      const message = err instanceof Error ? err.message : 'Failed to sign order';
      throw new Error(message);
    }
  }, [stats]);

  useEffect(() => {
    fetchDashboard();
  }, [fetchDashboard]);

  return {
    stats,
    pendingVerifications,
    pendingOrders,
    verificationHistory,
    loading,
    error,
    refetch: fetchDashboard,
    clearError,
    submitVerification,
    submitSignOrder,
  };
}

// ============================================================================
// useDoctorStats Hook
// ============================================================================

/**
 * Hook for doctor statistics only
 * 
 * Useful for displaying just the doctor counts without the full verification lists.
 * 
 * @returns Doctor stats state and operations
 * 
 * @example
 * ```typescript
 * function DoctorStatusBar() {
 *   const { stats, loading } = useDoctorStats();
 *   
 *   if (!stats) return null;
 *   
 *   return (
 *     <div>
 *       Pending: {stats.pendingVerifications} | 
 *       Orders to Sign: {stats.ordersToSign} | 
 *       Reviewed Today: {stats.reviewedToday}
 *     </div>
 *   );
 * }
 * ```
 */
export function useDoctorStats(): UseDoctorStatsReturn {
  const [stats, setStats] = useState<DoctorStats | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const fetchStats = useCallback(async () => {
    try {
      setLoading(true);
      setError(null);
      const doctorStats = await getDoctorStats();
      setStats(doctorStats);
    } catch (err) {
      const message = err instanceof Error ? err.message : 'Failed to load doctor statistics';
      setError(message);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    fetchStats();
  }, [fetchStats]);

  return {
    stats,
    loading,
    error,
    refetch: fetchStats,
  };
}

// ============================================================================
// usePendingVerifications Hook
// ============================================================================

/**
 * Hook for pending DOT verifications list
 * 
 * Fetches the list of DOT tests awaiting MRO verification.
 * 
 * @param initialLimit - Initial limit for results (default: 20)
 * @returns Pending verifications state and operations
 * 
 * @example
 * ```typescript
 * function VerificationQueue() {
 *   const { pendingVerifications, loading, verify, refetch } = usePendingVerifications(10);
 *   
 *   const handleVerify = async (testId: string) => {
 *     await verify(testId, 'negative');
 *     // List is automatically updated
 *   };
 *   
 *   return (
 *     <div>
 *       {pendingVerifications.map(v => (
 *         <VerificationCard key={v.id} verification={v} onVerify={handleVerify} />
 *       ))}
 *     </div>
 *   );
 * }
 * ```
 */
export function usePendingVerifications(initialLimit = 20): UsePendingVerificationsReturn {
  const [pendingVerifications, setPendingVerifications] = useState<PendingVerification[]>([]);
  const [limit, setLimitState] = useState(initialLimit);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const fetchVerifications = useCallback(async () => {
    try {
      setLoading(true);
      setError(null);
      const verifications = await getPendingVerifications(limit);
      setPendingVerifications(verifications);
    } catch (err) {
      const message = err instanceof Error ? err.message : 'Failed to load pending verifications';
      setError(message);
    } finally {
      setLoading(false);
    }
  }, [limit]);

  useEffect(() => {
    fetchVerifications();
  }, [fetchVerifications]);

  const setLimit = useCallback((newLimit: number) => {
    setLimitState(Math.min(Math.max(1, newLimit), 100));
  }, []);

  const verify = useCallback(async (
    testId: string,
    result: VerificationResult,
    comments?: string
  ) => {
    await verifyTest(testId, result, comments);
    // Remove verified item from list
    setPendingVerifications(prev => prev.filter(v => v.id !== testId));
  }, []);

  return {
    pendingVerifications,
    loading,
    error,
    refetch: fetchVerifications,
    setLimit,
    verify,
  };
}

// ============================================================================
// useVerificationHistory Hook
// ============================================================================

/**
 * Hook for verification history list
 * 
 * Fetches recently verified tests.
 * 
 * @param initialLimit - Initial limit for results (default: 20)
 * @returns Verification history state and operations
 * 
 * @example
 * ```typescript
 * function VerificationHistoryList() {
 *   const { history, loading } = useVerificationHistory(10);
 *   
 *   return (
 *     <div>
 *       {history.map(h => (
 *         <HistoryRow key={h.id} entry={h} />
 *       ))}
 *     </div>
 *   );
 * }
 * ```
 */
export function useVerificationHistory(initialLimit = 20): UseVerificationHistoryReturn {
  const [history, setHistory] = useState<VerificationHistory[]>([]);
  const [limit, setLimitState] = useState(initialLimit);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const fetchHistory = useCallback(async () => {
    try {
      setLoading(true);
      setError(null);
      const historyData = await getVerificationHistory(limit);
      setHistory(historyData);
    } catch (err) {
      const message = err instanceof Error ? err.message : 'Failed to load verification history';
      setError(message);
    } finally {
      setLoading(false);
    }
  }, [limit]);

  useEffect(() => {
    fetchHistory();
  }, [fetchHistory]);

  const setLimit = useCallback((newLimit: number) => {
    setLimitState(Math.min(Math.max(1, newLimit), 100));
  }, []);

  return {
    history,
    loading,
    error,
    refetch: fetchHistory,
    setLimit,
  };
}

// ============================================================================
// useTestDetails Hook
// ============================================================================

/**
 * Hook for test details for MRO review
 * 
 * Fetches comprehensive test details including patient info, chain of custody,
 * and test results.
 * 
 * @param testId - Test UUID to fetch details for
 * @returns Test details state and operations
 * 
 * @example
 * ```typescript
 * function TestReviewModal({ testId, onClose }) {
 *   const { test, loading, verify } = useTestDetails(testId);
 *   
 *   const handleVerify = async (result: VerificationResult) => {
 *     await verify(result, 'Verification comment');
 *     onClose();
 *   };
 *   
 *   if (loading) return <LoadingSpinner />;
 *   if (!test) return <NotFound />;
 *   
 *   return (
 *     <div>
 *       <PatientInfo patient={test.patient} />
 *       <TestInfo test={test.testInfo} />
 *       <ChainOfCustodyStatus coc={test.chainOfCustody} />
 *       <VerificationForm onSubmit={handleVerify} />
 *     </div>
 *   );
 * }
 * ```
 */
export function useTestDetails(testId: string): UseTestDetailsReturn {
  const [test, setTest] = useState<TestDetails | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const fetchDetails = useCallback(async () => {
    if (!testId) {
      setTest(null);
      setLoading(false);
      return;
    }

    try {
      setLoading(true);
      setError(null);
      const testDetails = await getTestDetails(testId);
      setTest(testDetails);
    } catch (err) {
      const message = err instanceof Error ? err.message : 'Failed to load test details';
      setError(message);
    } finally {
      setLoading(false);
    }
  }, [testId]);

  useEffect(() => {
    fetchDetails();
  }, [fetchDetails]);

  const verify = useCallback(async (result: VerificationResult, comments?: string) => {
    if (!testId) {
      throw new Error('No test ID provided');
    }
    await verifyTest(testId, result, comments);
  }, [testId]);

  return {
    test,
    loading,
    error,
    refetch: fetchDetails,
    verify,
  };
}

// ============================================================================
// useDoctorPendingOrders Hook
// ============================================================================

/**
 * Hook for pending orders list requiring doctor signature
 * 
 * Fetches orders awaiting doctor signature.
 * 
 * @param initialLimit - Initial limit for results (default: 20)
 * @returns Pending orders state and operations
 * 
 * @example
 * ```typescript
 * function PendingOrdersQueue() {
 *   const { pendingOrders, loading, sign, refetch } = useDoctorPendingOrders(10);
 *   
 *   const handleSign = async (orderId: string) => {
 *     await sign(orderId);
 *     // List is automatically updated
 *   };
 *   
 *   return (
 *     <div>
 *       {pendingOrders.map(o => (
 *         <OrderCard key={o.id} order={o} onSign={handleSign} />
 *       ))}
 *     </div>
 *   );
 * }
 * ```
 */
export function useDoctorPendingOrders(initialLimit = 20): UseDoctorPendingOrdersReturn {
  const [pendingOrders, setPendingOrders] = useState<PendingOrder[]>([]);
  const [limit, setLimitState] = useState(initialLimit);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const fetchOrders = useCallback(async () => {
    try {
      setLoading(true);
      setError(null);
      const orders = await getPendingOrders(limit);
      setPendingOrders(orders);
    } catch (err) {
      const message = err instanceof Error ? err.message : 'Failed to load pending orders';
      setError(message);
    } finally {
      setLoading(false);
    }
  }, [limit]);

  useEffect(() => {
    fetchOrders();
  }, [fetchOrders]);

  const setLimit = useCallback((newLimit: number) => {
    setLimitState(Math.min(Math.max(1, newLimit), 100));
  }, []);

  const sign = useCallback(async (orderId: string) => {
    await signOrder(orderId);
    // Remove signed order from list
    setPendingOrders(prev => prev.filter(o => o.id !== orderId));
  }, []);

  return {
    pendingOrders,
    loading,
    error,
    refetch: fetchOrders,
    setLimit,
    sign,
  };
}

// ============================================================================
// Re-export types for convenience
// ============================================================================

export type {
  DoctorStats,
  DoctorDashboardData,
  PendingVerification,
  PendingOrder,
  VerificationHistory,
  TestDetails,
  VerificationResult,
} from '../services/doctor.service.js';

// ============================================================================
// Export Default
// ============================================================================

export default {
  useDoctor,
  useDoctorStats,
  usePendingVerifications,
  useVerificationHistory,
  useTestDetails,
  useDoctorPendingOrders,
};
