/**
 * Clinical Provider Dashboard Hook
 *
 * React hooks for clinical provider dashboard functionality including provider
 * statistics, active encounters, recent encounters, and pending orders.
 *
 * Part of the Clinical Provider Dashboard workflow: View → Hook → Service → API
 *
 * @module hooks/useClinicalProvider
 */

import { useState, useEffect, useCallback } from 'react';
import {
  getDashboardData,
  getStats,
  getActiveEncounters,
  getRecentEncounters,
  getPendingOrders,
  getPendingQAReviews,
} from '../services/clinicalprovider.service.js';
import type {
  ProviderStats,
  ActiveEncounter,
  RecentEncounter,
  PendingOrder,
  QAReview,
  ClinicalProviderDashboardData,
} from '../services/clinicalprovider.service.js';

// ============================================================================
// Types
// ============================================================================

/**
 * Return type for the main clinical provider dashboard hook
 */
interface UseClinicalProviderReturn {
  /** Provider statistics (active encounters, pending orders, completed today, QA reviews) */
  stats: ProviderStats | null;
  /** List of active encounters */
  activeEncounters: ActiveEncounter[];
  /** List of recent encounters */
  recentEncounters: RecentEncounter[];
  /** List of pending orders */
  pendingOrders: PendingOrder[];
  /** Loading state */
  loading: boolean;
  /** Error message if any */
  error: string | null;
  /** Refetch dashboard data */
  refetch: () => Promise<void>;
  /** Clear any errors */
  clearError: () => void;
}

/**
 * Return type for the provider stats only hook
 */
interface UseProviderStatsReturn {
  /** Provider statistics */
  stats: ProviderStats | null;
  /** Loading state */
  loading: boolean;
  /** Error message if any */
  error: string | null;
  /** Refetch provider stats */
  refetch: () => Promise<void>;
}

/**
 * Return type for the active encounters hook
 */
interface UseActiveEncountersReturn {
  /** List of active encounters */
  activeEncounters: ActiveEncounter[];
  /** Loading state */
  loading: boolean;
  /** Error message if any */
  error: string | null;
  /** Refetch active encounters */
  refetch: () => Promise<void>;
  /** Set the limit for results */
  setLimit: (limit: number) => void;
}

/**
 * Return type for the recent encounters hook
 */
interface UseRecentEncountersReturn {
  /** List of recent encounters */
  recentEncounters: RecentEncounter[];
  /** Loading state */
  loading: boolean;
  /** Error message if any */
  error: string | null;
  /** Refetch recent encounters */
  refetch: () => Promise<void>;
  /** Set the limit for results */
  setLimit: (limit: number) => void;
}

/**
 * Return type for the pending orders hook
 */
interface UsePendingOrdersReturn {
  /** List of pending orders */
  pendingOrders: PendingOrder[];
  /** Loading state */
  loading: boolean;
  /** Error message if any */
  error: string | null;
  /** Refetch pending orders */
  refetch: () => Promise<void>;
  /** Set the limit for results */
  setLimit: (limit: number) => void;
}

/**
 * Return type for the QA reviews hook
 */
interface UseQAReviewsReturn {
  /** List of QA reviews */
  qaReviews: QAReview[];
  /** Loading state */
  loading: boolean;
  /** Error message if any */
  error: string | null;
  /** Refetch QA reviews */
  refetch: () => Promise<void>;
  /** Set the limit for results */
  setLimit: (limit: number) => void;
}

// ============================================================================
// useClinicalProvider Hook (Main Dashboard Hook)
// ============================================================================

/**
 * Main hook for clinical provider dashboard data
 * 
 * Fetches complete dashboard data on mount and provides refetch capability.
 * 
 * @returns Clinical provider dashboard state and operations
 * 
 * @example
 * ```typescript
 * function ClinicalProviderDashboard() {
 *   const { stats, activeEncounters, recentEncounters, loading, error, refetch } = useClinicalProvider();
 *   
 *   if (loading) return <LoadingSpinner />;
 *   if (error) return <ErrorMessage message={error} />;
 *   
 *   return (
 *     <div>
 *       <StatsDisplay stats={stats} />
 *       <EncounterList encounters={activeEncounters} />
 *       <button onClick={refetch}>Refresh</button>
 *     </div>
 *   );
 * }
 * ```
 */
export function useClinicalProvider(): UseClinicalProviderReturn {
  const [stats, setStats] = useState<ProviderStats | null>(null);
  const [activeEncounters, setActiveEncounters] = useState<ActiveEncounter[]>([]);
  const [recentEncounters, setRecentEncounters] = useState<RecentEncounter[]>([]);
  const [pendingOrders, setPendingOrders] = useState<PendingOrder[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const fetchDashboard = useCallback(async () => {
    try {
      setLoading(true);
      setError(null);
      const data = await getDashboardData();
      setStats(data.stats);
      setActiveEncounters(data.activeEncounters);
      setRecentEncounters(data.recentEncounters);
      setPendingOrders(data.pendingOrders);
    } catch (err) {
      const message = err instanceof Error ? err.message : 'Failed to load clinical provider dashboard';
      setError(message);
    } finally {
      setLoading(false);
    }
  }, []);

  const clearError = useCallback(() => {
    setError(null);
  }, []);

  useEffect(() => {
    fetchDashboard();
  }, [fetchDashboard]);

  return {
    stats,
    activeEncounters,
    recentEncounters,
    pendingOrders,
    loading,
    error,
    refetch: fetchDashboard,
    clearError,
  };
}

// ============================================================================
// useProviderStats Hook
// ============================================================================

/**
 * Hook for provider statistics only
 * 
 * Useful for displaying just the provider counts without the full encounter lists.
 * 
 * @returns Provider stats state and operations
 * 
 * @example
 * ```typescript
 * function ProviderStatusBar() {
 *   const { stats, loading } = useProviderStats();
 *   
 *   if (!stats) return null;
 *   
 *   return (
 *     <div>
 *       Active: {stats.activeEncounters} | 
 *       Pending Orders: {stats.pendingOrders} | 
 *       Completed: {stats.completedToday}
 *     </div>
 *   );
 * }
 * ```
 */
export function useProviderStats(): UseProviderStatsReturn {
  const [stats, setStats] = useState<ProviderStats | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const fetchStats = useCallback(async () => {
    try {
      setLoading(true);
      setError(null);
      const providerStats = await getStats();
      setStats(providerStats);
    } catch (err) {
      const message = err instanceof Error ? err.message : 'Failed to load provider statistics';
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
// useActiveEncounters Hook
// ============================================================================

/**
 * Hook for active encounters list
 * 
 * Fetches the list of encounters currently in progress or awaiting provider.
 * 
 * @param initialLimit - Initial limit for results (default: 10)
 * @returns Active encounters state and operations
 * 
 * @example
 * ```typescript
 * function ActiveEncounterQueue() {
 *   const { activeEncounters, loading, setLimit } = useActiveEncounters(5);
 *   
 *   return (
 *     <div>
 *       {activeEncounters.map(e => (
 *         <EncounterCard key={e.id} encounter={e} />
 *       ))}
 *       <button onClick={() => setLimit(10)}>Show More</button>
 *     </div>
 *   );
 * }
 * ```
 */
export function useActiveEncounters(initialLimit = 10): UseActiveEncountersReturn {
  const [activeEncounters, setActiveEncounters] = useState<ActiveEncounter[]>([]);
  const [limit, setLimitState] = useState(initialLimit);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const fetchActive = useCallback(async () => {
    try {
      setLoading(true);
      setError(null);
      const encounters = await getActiveEncounters(limit);
      setActiveEncounters(encounters);
    } catch (err) {
      const message = err instanceof Error ? err.message : 'Failed to load active encounters';
      setError(message);
    } finally {
      setLoading(false);
    }
  }, [limit]);

  useEffect(() => {
    fetchActive();
  }, [fetchActive]);

  const setLimit = useCallback((newLimit: number) => {
    setLimitState(Math.min(Math.max(1, newLimit), 50));
  }, []);

  return {
    activeEncounters,
    loading,
    error,
    refetch: fetchActive,
    setLimit,
  };
}

// ============================================================================
// useRecentEncounters Hook
// ============================================================================

/**
 * Hook for recent encounters list
 * 
 * Fetches recently completed encounters for follow-up purposes.
 * 
 * @param initialLimit - Initial limit for results (default: 10)
 * @returns Recent encounters state and operations
 * 
 * @example
 * ```typescript
 * function RecentEncountersList() {
 *   const { recentEncounters, loading } = useRecentEncounters(5);
 *   
 *   return (
 *     <div>
 *       {recentEncounters.map(e => (
 *         <RecentEncounterRow key={e.id} encounter={e} />
 *       ))}
 *     </div>
 *   );
 * }
 * ```
 */
export function useRecentEncounters(initialLimit = 10): UseRecentEncountersReturn {
  const [recentEncounters, setRecentEncounters] = useState<RecentEncounter[]>([]);
  const [limit, setLimitState] = useState(initialLimit);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const fetchRecent = useCallback(async () => {
    try {
      setLoading(true);
      setError(null);
      const encounters = await getRecentEncounters(limit);
      setRecentEncounters(encounters);
    } catch (err) {
      const message = err instanceof Error ? err.message : 'Failed to load recent encounters';
      setError(message);
    } finally {
      setLoading(false);
    }
  }, [limit]);

  useEffect(() => {
    fetchRecent();
  }, [fetchRecent]);

  const setLimit = useCallback((newLimit: number) => {
    setLimitState(Math.min(Math.max(1, newLimit), 50));
  }, []);

  return {
    recentEncounters,
    loading,
    error,
    refetch: fetchRecent,
    setLimit,
  };
}

// ============================================================================
// usePendingOrders Hook
// ============================================================================

/**
 * Hook for pending orders list
 * 
 * Fetches orders awaiting provider signature or review.
 * 
 * @param initialLimit - Initial limit for results (default: 10)
 * @returns Pending orders state and operations
 * 
 * @example
 * ```typescript
 * function PendingOrdersQueue() {
 *   const { pendingOrders, loading, refetch } = usePendingOrders(10);
 *   
 *   return (
 *     <div>
 *       {pendingOrders.map(o => (
 *         <OrderCard key={o.id} order={o} onSign={() => refetch()} />
 *       ))}
 *     </div>
 *   );
 * }
 * ```
 */
export function usePendingOrders(initialLimit = 10): UsePendingOrdersReturn {
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
    setLimitState(Math.min(Math.max(1, newLimit), 50));
  }, []);

  return {
    pendingOrders,
    loading,
    error,
    refetch: fetchOrders,
    setLimit,
  };
}

// ============================================================================
// useQAReviews Hook
// ============================================================================

/**
 * Hook for pending QA reviews
 * 
 * Fetches QA review items assigned to or available for the provider.
 * 
 * @param initialLimit - Initial limit for results (default: 10)
 * @returns QA reviews state and operations
 * 
 * @example
 * ```typescript
 * function QAReviewQueue() {
 *   const { qaReviews, loading, refetch } = useQAReviews(10);
 *   
 *   return (
 *     <div>
 *       {qaReviews.map(r => (
 *         <ReviewCard key={r.id} review={r} onComplete={() => refetch()} />
 *       ))}
 *     </div>
 *   );
 * }
 * ```
 */
export function useQAReviews(initialLimit = 10): UseQAReviewsReturn {
  const [qaReviews, setQAReviews] = useState<QAReview[]>([]);
  const [limit, setLimitState] = useState(initialLimit);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const fetchReviews = useCallback(async () => {
    try {
      setLoading(true);
      setError(null);
      const reviews = await getPendingQAReviews(limit);
      setQAReviews(reviews);
    } catch (err) {
      const message = err instanceof Error ? err.message : 'Failed to load QA reviews';
      setError(message);
    } finally {
      setLoading(false);
    }
  }, [limit]);

  useEffect(() => {
    fetchReviews();
  }, [fetchReviews]);

  const setLimit = useCallback((newLimit: number) => {
    setLimitState(Math.min(Math.max(1, newLimit), 50));
  }, []);

  return {
    qaReviews,
    loading,
    error,
    refetch: fetchReviews,
    setLimit,
  };
}

// ============================================================================
// Re-export types for convenience
// ============================================================================

export type {
  ProviderStats,
  ActiveEncounter,
  RecentEncounter,
  PendingOrder,
  QAReview,
  ClinicalProviderDashboardData,
} from '../services/clinicalprovider.service.js';

// ============================================================================
// Export Default
// ============================================================================

export default {
  useClinicalProvider,
  useProviderStats,
  useActiveEncounters,
  useRecentEncounters,
  usePendingOrders,
  useQAReviews,
};
