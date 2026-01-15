/**
 * Dashboard Data Hook for SafeShift EHR
 * 
 * Provides React hooks for fetching and managing dashboard data
 * for different roles (Manager, Clinical Provider, Technician, Registration).
 */

import { useState, useCallback, useEffect } from 'react';
import {
  dashboardService,
  getErrorMessage,
  type ManagerStats,
  type Case,
  type ClinicalProviderStats,
  type TechnicianStats,
  type RegistrationStats,
} from '../services/index.js';

// ============================================================================
// Types
// ============================================================================

interface PaginationState {
  page: number;
  perPage: number;
  total: number;
  totalPages: number;
}

interface UseManagerDashboardReturn {
  stats: ManagerStats | null;
  cases: Case[];
  loading: boolean;
  error: string | null;
  pagination: PaginationState;
  fetchDashboard: () => Promise<void>;
  fetchCases: (status?: string, page?: number) => Promise<void>;
  addFlag: (caseId: string, flagType: string, reason: string, severity?: string) => Promise<boolean>;
  resolveFlag: (flagId: string) => Promise<boolean>;
  clearError: () => void;
  refetch: () => Promise<void>;
}

interface UseClinicalDashboardReturn {
  stats: ClinicalProviderStats | null;
  pendingEncounters: object[];
  todaysEncounters: object[];
  loading: boolean;
  error: string | null;
  fetchDashboard: () => Promise<void>;
  refetch: () => Promise<void>;
  clearError: () => void;
}

interface UseTechnicianDashboardReturn {
  stats: TechnicianStats | null;
  taskQueue: object[];
  loading: boolean;
  error: string | null;
  fetchDashboard: () => Promise<void>;
  refetch: () => Promise<void>;
  clearError: () => void;
}

interface UseRegistrationDashboardReturn {
  stats: RegistrationStats | null;
  appointments: object[];
  loading: boolean;
  error: string | null;
  fetchDashboard: () => Promise<void>;
  refetch: () => Promise<void>;
  clearError: () => void;
}

// ============================================================================
// useManagerDashboard Hook
// ============================================================================

/**
 * Hook for manager dashboard data and operations
 * @returns Manager dashboard state and operations
 */
export function useManagerDashboard(): UseManagerDashboardReturn {
  const [stats, setStats] = useState<ManagerStats | null>(null);
  const [cases, setCases] = useState<Case[]>([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [pagination, setPagination] = useState<PaginationState>({
    page: 1,
    perPage: 20,
    total: 0,
    totalPages: 0,
  });

  /**
   * Fetch full manager dashboard data
   */
  const fetchDashboard = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const data = await dashboardService.getManagerDashboard();
      setStats(data.stats);
      setCases(data.cases || []);
    } catch (err: unknown) {
      setError(getErrorMessage(err, 'Failed to fetch dashboard data'));
    } finally {
      setLoading(false);
    }
  }, []);

  /**
   * Fetch cases with filtering and pagination
   */
  const fetchCases = useCallback(async (status?: string, page = 1) => {
    setLoading(true);
    setError(null);
    try {
      const result = await dashboardService.getCases(status, page, pagination.perPage);
      setCases(result.cases || []);
      setPagination({
        page: result.pagination.page,
        perPage: result.pagination.per_page,
        total: result.pagination.total,
        totalPages: result.pagination.total_pages,
      });
    } catch (err: unknown) {
      setError(getErrorMessage(err, 'Failed to fetch cases'));
    } finally {
      setLoading(false);
    }
  }, [pagination.perPage]);

  /**
   * Add a flag to a case
   */
  const addFlag = useCallback(async (
    caseId: string,
    flagType: string,
    reason: string,
    severity = 'medium'
  ): Promise<boolean> => {
    setLoading(true);
    setError(null);
    try {
      const result = await dashboardService.addCaseFlag(caseId, flagType, reason, severity);
      // Update the case in the list
      if (result.case) {
        setCases(prev => prev.map(c => c.id === caseId ? result.case : c));
      }
      return true;
    } catch (err: unknown) {
      setError(getErrorMessage(err, 'Failed to add flag'));
      return false;
    } finally {
      setLoading(false);
    }
  }, []);

  /**
   * Resolve a flag
   */
  const resolveFlag = useCallback(async (flagId: string): Promise<boolean> => {
    setLoading(true);
    setError(null);
    try {
      await dashboardService.resolveFlag(flagId);
      // Refresh dashboard to get updated data
      await fetchDashboard();
      return true;
    } catch (err: unknown) {
      setError(getErrorMessage(err, 'Failed to resolve flag'));
      return false;
    } finally {
      setLoading(false);
    }
  }, [fetchDashboard]);

  /**
   * Clear error state
   */
  const clearError = useCallback(() => {
    setError(null);
  }, []);

  // Fetch dashboard on mount
  useEffect(() => {
    fetchDashboard();
  }, [fetchDashboard]);

  return {
    stats,
    cases,
    loading,
    error,
    pagination,
    fetchDashboard,
    fetchCases,
    addFlag,
    resolveFlag,
    clearError,
    refetch: fetchDashboard,
  };
}

// ============================================================================
// useClinicalDashboard Hook
// ============================================================================

/**
 * Hook for clinical provider dashboard data
 * @returns Clinical dashboard state and operations
 */
export function useClinicalDashboard(): UseClinicalDashboardReturn {
  const [stats, setStats] = useState<ClinicalProviderStats | null>(null);
  const [pendingEncounters, setPendingEncounters] = useState<object[]>([]);
  const [todaysEncounters, setTodaysEncounters] = useState<object[]>([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const fetchDashboard = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const data = await dashboardService.getClinicalProviderDashboard();
      setStats(data.stats);
      setPendingEncounters(data.pendingEncounters || []);
      setTodaysEncounters(data.todaysEncounters || []);
    } catch (err: unknown) {
      setError(getErrorMessage(err, 'Failed to fetch dashboard data'));
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
    pendingEncounters,
    todaysEncounters,
    loading,
    error,
    fetchDashboard,
    refetch: fetchDashboard,
    clearError,
  };
}

// ============================================================================
// useTechnicianDashboard Hook
// ============================================================================

/**
 * Hook for technician dashboard data
 * @returns Technician dashboard state and operations
 */
export function useTechnicianDashboard(): UseTechnicianDashboardReturn {
  const [stats, setStats] = useState<TechnicianStats | null>(null);
  const [taskQueue, setTaskQueue] = useState<object[]>([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const fetchDashboard = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const data = await dashboardService.getTechnicianDashboard();
      setStats(data.stats);
      setTaskQueue(data.taskQueue || []);
    } catch (err: unknown) {
      setError(getErrorMessage(err, 'Failed to fetch dashboard data'));
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
    taskQueue,
    loading,
    error,
    fetchDashboard,
    refetch: fetchDashboard,
    clearError,
  };
}

// ============================================================================
// useRegistrationDashboard Hook
// ============================================================================

/**
 * Hook for registration dashboard data
 * @returns Registration dashboard state and operations
 */
export function useRegistrationDashboard(): UseRegistrationDashboardReturn {
  const [stats, setStats] = useState<RegistrationStats | null>(null);
  const [appointments, setAppointments] = useState<object[]>([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const fetchDashboard = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const data = await dashboardService.getRegistrationDashboard();
      setStats(data.stats);
      setAppointments(data.appointments || []);
    } catch (err: unknown) {
      setError(getErrorMessage(err, 'Failed to fetch dashboard data'));
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
    appointments,
    loading,
    error,
    fetchDashboard,
    refetch: fetchDashboard,
    clearError,
  };
}

// ============================================================================
// Default Export
// ============================================================================

export default useManagerDashboard;
