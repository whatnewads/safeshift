/**
 * SuperManager Dashboard Hook
 *
 * React hooks for SuperManager dashboard functionality including multi-clinic
 * oversight, staff management, operational metrics, and approval actions.
 *
 * Part of the SuperManager Dashboard workflow: View → Hook → Service → API
 *
 * The SuperManager role is associated with 'Manager' role type with elevated
 * permissions across multiple establishments/clinics.
 *
 * @module hooks/useSuperManager
 */

import { useState, useEffect, useCallback } from 'react';
import {
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
} from '../services/supermanager.service.js';
import type {
  SuperManagerStats,
  ClinicPerformance,
  ClinicComparison,
  StaffMember,
  ExpiringCredential,
  OverdueTraining,
  PendingApproval,
  StaffRequiringAttention,
} from '../services/supermanager.service.js';

// ============================================================================
// Types
// ============================================================================

/**
 * Return type for the main SuperManager dashboard hook
 */
interface UseSuperManagerReturn {
  /** SuperManager statistics */
  stats: SuperManagerStats | null;
  /** List of clinic performance metrics */
  clinicPerformance: ClinicPerformance[];
  /** Staff requiring attention (expiring credentials and overdue training) */
  staffRequiringAttention: StaffRequiringAttention | null;
  /** List of pending approvals */
  pendingApprovals: PendingApproval[];
  /** List of staff members */
  staffOverview: StaffMember[];
  /** Loading state */
  loading: boolean;
  /** Error message if any */
  error: string | null;
  /** Refetch dashboard data */
  refetch: () => Promise<void>;
  /** Clear any errors */
  clearError: () => void;
  /** Approve a pending request */
  handleApprove: (requestId: string, type: string) => Promise<void>;
  /** Deny a pending request */
  handleDeny: (requestId: string, type: string, reason?: string) => Promise<void>;
}

/**
 * Return type for the SuperManager stats only hook
 */
interface UseSuperManagerStatsReturn {
  /** SuperManager statistics */
  stats: SuperManagerStats | null;
  /** Loading state */
  loading: boolean;
  /** Error message if any */
  error: string | null;
  /** Refetch stats */
  refetch: () => Promise<void>;
}

/**
 * Return type for the clinic performance hook
 */
interface UseClinicPerformanceReturn {
  /** List of clinic performance metrics */
  clinics: ClinicPerformance[];
  /** Loading state */
  loading: boolean;
  /** Error message if any */
  error: string | null;
  /** Refetch clinics */
  refetch: () => Promise<void>;
  /** Set the limit for results */
  setLimit: (limit: number) => void;
}

/**
 * Return type for the clinic comparison hook
 */
interface UseClinicComparisonReturn {
  /** List of clinic comparison metrics */
  comparison: ClinicComparison[];
  /** Loading state */
  loading: boolean;
  /** Error message if any */
  error: string | null;
  /** Refetch comparison */
  refetch: () => Promise<void>;
}

/**
 * Return type for the staff overview hook
 */
interface UseStaffOverviewReturn {
  /** List of staff members */
  staff: StaffMember[];
  /** Loading state */
  loading: boolean;
  /** Error message if any */
  error: string | null;
  /** Refetch staff */
  refetch: () => Promise<void>;
  /** Set the limit for results */
  setLimit: (limit: number) => void;
}

/**
 * Return type for the expiring credentials hook
 */
interface UseExpiringCredentialsReturn {
  /** List of expiring credentials */
  credentials: ExpiringCredential[];
  /** Loading state */
  loading: boolean;
  /** Error message if any */
  error: string | null;
  /** Refetch credentials */
  refetch: () => Promise<void>;
  /** Set days ahead to look */
  setDaysAhead: (days: number) => void;
  /** Set the limit for results */
  setLimit: (limit: number) => void;
}

/**
 * Return type for the overdue training hook
 */
interface UseTrainingOverdueReturn {
  /** List of overdue training */
  overdue: OverdueTraining[];
  /** Loading state */
  loading: boolean;
  /** Error message if any */
  error: string | null;
  /** Refetch overdue training */
  refetch: () => Promise<void>;
  /** Set the limit for results */
  setLimit: (limit: number) => void;
}

/**
 * Return type for the pending approvals hook
 */
interface UsePendingApprovalsReturn {
  /** List of pending approvals */
  approvals: PendingApproval[];
  /** Loading state */
  loading: boolean;
  /** Error message if any */
  error: string | null;
  /** Refetch approvals */
  refetch: () => Promise<void>;
  /** Set the limit for results */
  setLimit: (limit: number) => void;
  /** Approve a request */
  approve: (requestId: string, type: string) => Promise<void>;
  /** Deny a request */
  deny: (requestId: string, type: string, reason?: string) => Promise<void>;
}

// ============================================================================
// useSuperManager Hook (Main Dashboard Hook)
// ============================================================================

/**
 * Main hook for SuperManager dashboard data
 * 
 * Fetches complete dashboard data on mount and provides refetch capability.
 * Also provides methods for approving and denying requests.
 * 
 * @returns SuperManager dashboard state and operations
 * 
 * @example
 * ```typescript
 * function SuperManagerDashboard() {
 *   const { stats, clinicPerformance, loading, error, handleApprove, refetch } = useSuperManager();
 *   
 *   if (loading) return <LoadingSpinner />;
 *   if (error) return <ErrorMessage message={error} />;
 *   
 *   const onApprove = async (id: string, type: string) => {
 *     await handleApprove(id, type);
 *     await refetch();
 *   };
 *   
 *   return (
 *     <div>
 *       <StatsDisplay stats={stats} />
 *       <ClinicTable clinics={clinicPerformance} />
 *     </div>
 *   );
 * }
 * ```
 */
export function useSuperManager(): UseSuperManagerReturn {
  const [stats, setStats] = useState<SuperManagerStats | null>(null);
  const [clinicPerformance, setClinicPerformance] = useState<ClinicPerformance[]>([]);
  const [staffRequiringAttention, setStaffRequiringAttention] = useState<StaffRequiringAttention | null>(null);
  const [pendingApprovals, setPendingApprovals] = useState<PendingApproval[]>([]);
  const [staffOverview, setStaffOverview] = useState<StaffMember[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const fetchDashboard = useCallback(async () => {
    try {
      setLoading(true);
      setError(null);
      const data = await getDashboardData();
      setStats(data.stats);
      setClinicPerformance(data.clinicPerformance);
      setStaffRequiringAttention(data.staffRequiringAttention);
      setPendingApprovals(data.pendingApprovals);
      setStaffOverview(data.staffOverview);
    } catch (err) {
      const message = err instanceof Error ? err.message : 'Failed to load SuperManager dashboard';
      setError(message);
    } finally {
      setLoading(false);
    }
  }, []);

  const clearError = useCallback(() => {
    setError(null);
  }, []);

  const handleApprove = useCallback(async (requestId: string, type: string) => {
    try {
      await approvePending(requestId, type);
      // Remove the approved item from pending list
      setPendingApprovals(prev => prev.filter(a => a.id !== requestId));
      // Update stats
      if (stats) {
        setStats({
          ...stats,
          pendingApprovals: Math.max(0, stats.pendingApprovals - 1),
        });
      }
    } catch (err) {
      const message = err instanceof Error ? err.message : 'Failed to approve request';
      throw new Error(message);
    }
  }, [stats]);

  const handleDeny = useCallback(async (requestId: string, type: string, reason?: string) => {
    try {
      await denyPending(requestId, type, reason);
      // Remove the denied item from pending list
      setPendingApprovals(prev => prev.filter(a => a.id !== requestId));
      // Update stats
      if (stats) {
        setStats({
          ...stats,
          pendingApprovals: Math.max(0, stats.pendingApprovals - 1),
        });
      }
    } catch (err) {
      const message = err instanceof Error ? err.message : 'Failed to deny request';
      throw new Error(message);
    }
  }, [stats]);

  useEffect(() => {
    fetchDashboard();
  }, [fetchDashboard]);

  return {
    stats,
    clinicPerformance,
    staffRequiringAttention,
    pendingApprovals,
    staffOverview,
    loading,
    error,
    refetch: fetchDashboard,
    clearError,
    handleApprove,
    handleDeny,
  };
}

// ============================================================================
// useSuperManagerStats Hook
// ============================================================================

/**
 * Hook for SuperManager statistics only
 * 
 * Useful for displaying just the overview counts without the full lists.
 * 
 * @returns SuperManager stats state and operations
 * 
 * @example
 * ```typescript
 * function SuperManagerStatusBar() {
 *   const { stats, loading } = useSuperManagerStats();
 *   
 *   if (!stats) return null;
 *   
 *   return (
 *     <div>
 *       Clinics: {stats.clinicsManaged} | 
 *       Staff: {stats.totalStaff} | 
 *       Approvals: {stats.pendingApprovals}
 *     </div>
 *   );
 * }
 * ```
 */
export function useSuperManagerStats(): UseSuperManagerStatsReturn {
  const [stats, setStats] = useState<SuperManagerStats | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const fetchStats = useCallback(async () => {
    try {
      setLoading(true);
      setError(null);
      const superManagerStats = await getOverviewStats();
      setStats(superManagerStats);
    } catch (err) {
      const message = err instanceof Error ? err.message : 'Failed to load SuperManager statistics';
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
// useClinicPerformance Hook
// ============================================================================

/**
 * Hook for clinic performance metrics
 * 
 * Fetches performance metrics for all managed clinics.
 * 
 * @param initialLimit - Initial limit for results (default: 10)
 * @returns Clinic performance state and operations
 * 
 * @example
 * ```typescript
 * function ClinicPerformanceTable() {
 *   const { clinics, loading, refetch } = useClinicPerformance(10);
 *   
 *   return (
 *     <div>
 *       {clinics.map(c => (
 *         <ClinicRow key={c.id} clinic={c} />
 *       ))}
 *     </div>
 *   );
 * }
 * ```
 */
export function useClinicPerformance(initialLimit = 10): UseClinicPerformanceReturn {
  const [clinics, setClinics] = useState<ClinicPerformance[]>([]);
  const [limit, setLimitState] = useState(initialLimit);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const fetchClinics = useCallback(async () => {
    try {
      setLoading(true);
      setError(null);
      const clinicData = await getClinicPerformance(limit);
      setClinics(clinicData);
    } catch (err) {
      const message = err instanceof Error ? err.message : 'Failed to load clinic performance';
      setError(message);
    } finally {
      setLoading(false);
    }
  }, [limit]);

  useEffect(() => {
    fetchClinics();
  }, [fetchClinics]);

  const setLimit = useCallback((newLimit: number) => {
    setLimitState(Math.min(Math.max(1, newLimit), 50));
  }, []);

  return {
    clinics,
    loading,
    error,
    refetch: fetchClinics,
    setLimit,
  };
}

// ============================================================================
// useClinicComparison Hook
// ============================================================================

/**
 * Hook for clinic comparison metrics
 * 
 * Fetches comparative metrics across all clinics.
 * 
 * @returns Clinic comparison state and operations
 * 
 * @example
 * ```typescript
 * function ClinicComparisonChart() {
 *   const { comparison, loading } = useClinicComparison();
 *   
 *   return (
 *     <BarChart data={comparison} />
 *   );
 * }
 * ```
 */
export function useClinicComparison(): UseClinicComparisonReturn {
  const [comparison, setComparison] = useState<ClinicComparison[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const fetchComparison = useCallback(async () => {
    try {
      setLoading(true);
      setError(null);
      const comparisonData = await getClinicComparison();
      setComparison(comparisonData);
    } catch (err) {
      const message = err instanceof Error ? err.message : 'Failed to load clinic comparison';
      setError(message);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    fetchComparison();
  }, [fetchComparison]);

  return {
    comparison,
    loading,
    error,
    refetch: fetchComparison,
  };
}

// ============================================================================
// useStaffOverview Hook
// ============================================================================

/**
 * Hook for staff overview list
 * 
 * Fetches staff members with roles and compliance status.
 * 
 * @param initialLimit - Initial limit for results (default: 20)
 * @returns Staff overview state and operations
 * 
 * @example
 * ```typescript
 * function StaffTable() {
 *   const { staff, loading } = useStaffOverview(20);
 *   
 *   return (
 *     <table>
 *       {staff.map(s => (
 *         <StaffRow key={s.id} staff={s} />
 *       ))}
 *     </table>
 *   );
 * }
 * ```
 */
export function useStaffOverview(initialLimit = 20): UseStaffOverviewReturn {
  const [staff, setStaff] = useState<StaffMember[]>([]);
  const [limit, setLimitState] = useState(initialLimit);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const fetchStaff = useCallback(async () => {
    try {
      setLoading(true);
      setError(null);
      const staffData = await getStaffOverview(limit);
      setStaff(staffData);
    } catch (err) {
      const message = err instanceof Error ? err.message : 'Failed to load staff overview';
      setError(message);
    } finally {
      setLoading(false);
    }
  }, [limit]);

  useEffect(() => {
    fetchStaff();
  }, [fetchStaff]);

  const setLimit = useCallback((newLimit: number) => {
    setLimitState(Math.min(Math.max(1, newLimit), 100));
  }, []);

  return {
    staff,
    loading,
    error,
    refetch: fetchStaff,
    setLimit,
  };
}

// ============================================================================
// useExpiringCredentials Hook
// ============================================================================

/**
 * Hook for expiring credentials list
 * 
 * Fetches credentials expiring within specified days.
 * 
 * @param initialDaysAhead - Initial days to look ahead (default: 30)
 * @param initialLimit - Initial limit for results (default: 10)
 * @returns Expiring credentials state and operations
 * 
 * @example
 * ```typescript
 * function ExpiringCredentialsAlert() {
 *   const { credentials, loading } = useExpiringCredentials(30, 5);
 *   
 *   return (
 *     <AlertList>
 *       {credentials.map(c => (
 *         <CredentialAlert key={c.id} credential={c} />
 *       ))}
 *     </AlertList>
 *   );
 * }
 * ```
 */
export function useExpiringCredentials(initialDaysAhead = 30, initialLimit = 10): UseExpiringCredentialsReturn {
  const [credentials, setCredentials] = useState<ExpiringCredential[]>([]);
  const [daysAhead, setDaysAheadState] = useState(initialDaysAhead);
  const [limit, setLimitState] = useState(initialLimit);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const fetchCredentials = useCallback(async () => {
    try {
      setLoading(true);
      setError(null);
      const credentialsData = await getExpiringCredentials(daysAhead, limit);
      setCredentials(credentialsData);
    } catch (err) {
      const message = err instanceof Error ? err.message : 'Failed to load expiring credentials';
      setError(message);
    } finally {
      setLoading(false);
    }
  }, [daysAhead, limit]);

  useEffect(() => {
    fetchCredentials();
  }, [fetchCredentials]);

  const setDaysAhead = useCallback((days: number) => {
    setDaysAheadState(Math.min(Math.max(1, days), 365));
  }, []);

  const setLimit = useCallback((newLimit: number) => {
    setLimitState(Math.min(Math.max(1, newLimit), 50));
  }, []);

  return {
    credentials,
    loading,
    error,
    refetch: fetchCredentials,
    setDaysAhead,
    setLimit,
  };
}

// ============================================================================
// useTrainingOverdue Hook
// ============================================================================

/**
 * Hook for overdue training list
 * 
 * Fetches staff with overdue training.
 * 
 * @param initialLimit - Initial limit for results (default: 10)
 * @returns Overdue training state and operations
 * 
 * @example
 * ```typescript
 * function OverdueTrainingAlert() {
 *   const { overdue, loading } = useTrainingOverdue(5);
 *   
 *   return (
 *     <AlertList>
 *       {overdue.map(o => (
 *         <TrainingAlert key={o.id} training={o} />
 *       ))}
 *     </AlertList>
 *   );
 * }
 * ```
 */
export function useTrainingOverdue(initialLimit = 10): UseTrainingOverdueReturn {
  const [overdue, setOverdue] = useState<OverdueTraining[]>([]);
  const [limit, setLimitState] = useState(initialLimit);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const fetchOverdue = useCallback(async () => {
    try {
      setLoading(true);
      setError(null);
      const overdueData = await getTrainingOverdue(limit);
      setOverdue(overdueData);
    } catch (err) {
      const message = err instanceof Error ? err.message : 'Failed to load overdue training';
      setError(message);
    } finally {
      setLoading(false);
    }
  }, [limit]);

  useEffect(() => {
    fetchOverdue();
  }, [fetchOverdue]);

  const setLimit = useCallback((newLimit: number) => {
    setLimitState(Math.min(Math.max(1, newLimit), 50));
  }, []);

  return {
    overdue,
    loading,
    error,
    refetch: fetchOverdue,
    setLimit,
  };
}

// ============================================================================
// usePendingApprovals Hook
// ============================================================================

/**
 * Hook for pending approvals list
 * 
 * Fetches pending approval requests with approve/deny actions.
 * 
 * @param initialLimit - Initial limit for results (default: 10)
 * @returns Pending approvals state and operations
 * 
 * @example
 * ```typescript
 * function ApprovalsQueue() {
 *   const { approvals, loading, approve, deny, refetch } = usePendingApprovals(10);
 *   
 *   const handleApprove = async (id: string, type: string) => {
 *     await approve(id, type);
 *   };
 *   
 *   return (
 *     <div>
 *       {approvals.map(a => (
 *         <ApprovalCard key={a.id} approval={a} onApprove={handleApprove} onDeny={handleDeny} />
 *       ))}
 *     </div>
 *   );
 * }
 * ```
 */
export function usePendingApprovals(initialLimit = 10): UsePendingApprovalsReturn {
  const [approvals, setApprovals] = useState<PendingApproval[]>([]);
  const [limit, setLimitState] = useState(initialLimit);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const fetchApprovals = useCallback(async () => {
    try {
      setLoading(true);
      setError(null);
      const approvalsData = await getPendingApprovals(limit);
      setApprovals(approvalsData);
    } catch (err) {
      const message = err instanceof Error ? err.message : 'Failed to load pending approvals';
      setError(message);
    } finally {
      setLoading(false);
    }
  }, [limit]);

  useEffect(() => {
    fetchApprovals();
  }, [fetchApprovals]);

  const setLimit = useCallback((newLimit: number) => {
    setLimitState(Math.min(Math.max(1, newLimit), 50));
  }, []);

  const approve = useCallback(async (requestId: string, type: string) => {
    await approvePending(requestId, type);
    // Remove approved item from list
    setApprovals(prev => prev.filter(a => a.id !== requestId));
  }, []);

  const deny = useCallback(async (requestId: string, type: string, reason?: string) => {
    await denyPending(requestId, type, reason);
    // Remove denied item from list
    setApprovals(prev => prev.filter(a => a.id !== requestId));
  }, []);

  return {
    approvals,
    loading,
    error,
    refetch: fetchApprovals,
    setLimit,
    approve,
    deny,
  };
}

// ============================================================================
// Re-export types for convenience
// ============================================================================

export type {
  SuperManagerStats,
  SuperManagerDashboardData,
  ClinicPerformance,
  ClinicComparison,
  StaffMember,
  ExpiringCredential,
  OverdueTraining,
  PendingApproval,
  StaffRequiringAttention,
} from '../services/supermanager.service.js';

// ============================================================================
// Export Default
// ============================================================================

export default {
  useSuperManager,
  useSuperManagerStats,
  useClinicPerformance,
  useClinicComparison,
  useStaffOverview,
  useExpiringCredentials,
  useTrainingOverdue,
  usePendingApprovals,
};
