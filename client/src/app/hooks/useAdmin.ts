/**
 * Admin Dashboard Hook
 *
 * React hooks for admin dashboard functionality including compliance,
 * training, OSHA data, cases, patient flow, and performance metrics.
 *
 * @module hooks/useAdmin
 */

import { useState, useEffect, useCallback } from 'react';
import {
  getAdminDashboard,
  getCaseStats,
  getRecentCases,
  getPatientFlowMetrics,
  getSitePerformance,
  getProviderPerformance,
  getStaffList,
  getClearanceStats,
  getComplianceAlerts,
  acknowledgeComplianceAlert,
  getTrainingModules,
  getExpiringCredentials,
  getOsha300Log,
  getOsha300ASummary,
} from '../services/admin.service.js';
import type {
  AdminStats,
  AdminDashboardData,
  PatientFlow,
  RecentCase,
  SitePerformance,
  ProviderPerformance,
  StaffMember,
  ClearanceStats,
  ComplianceAlert,
  TrainingModule,
  ExpiringCredential,
  Osha300Entry,
  OshaStatistics,
  Osha300AEntry,
} from '../services/admin.service.js';

// ============================================================================
// useAdminDashboard Hook - Full Dashboard Data
// ============================================================================

interface UseAdminDashboardReturn {
  data: AdminDashboardData | null;
  stats: AdminStats | null;
  patientFlow: PatientFlow | null;
  recentCases: RecentCase[];
  sitePerformance: SitePerformance[];
  providerPerformance: ProviderPerformance[];
  clearanceStats: ClearanceStats | null;
  loading: boolean;
  error: string | null;
  refetch: () => Promise<void>;
}

/**
 * Hook for full admin dashboard data (all data in one call)
 */
export function useAdminDashboard(): UseAdminDashboardReturn {
  const [data, setData] = useState<AdminDashboardData | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const fetchDashboard = useCallback(async () => {
    try {
      setLoading(true);
      setError(null);
      const dashboardData = await getAdminDashboard();
      setData(dashboardData);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to load admin dashboard');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    fetchDashboard();
  }, [fetchDashboard]);

  return {
    data,
    stats: data?.stats ?? null,
    patientFlow: data?.patientFlow ?? null,
    recentCases: data?.recentCases ?? [],
    sitePerformance: data?.sitePerformance ?? [],
    providerPerformance: data?.providerPerformance ?? [],
    clearanceStats: data?.clearanceStats ?? null,
    loading,
    error,
    refetch: fetchDashboard,
  };
}

// ============================================================================
// useCaseStats Hook
// ============================================================================

interface UseCaseStatsReturn {
  stats: AdminStats | null;
  loading: boolean;
  error: string | null;
  refetch: () => Promise<void>;
}

/**
 * Hook for case statistics only
 */
export function useCaseStats(): UseCaseStatsReturn {
  const [stats, setStats] = useState<AdminStats | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const fetchStats = useCallback(async () => {
    try {
      setLoading(true);
      setError(null);
      const data = await getCaseStats();
      setStats(data);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to load case statistics');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    fetchStats();
  }, [fetchStats]);

  return { stats, loading, error, refetch: fetchStats };
}

// ============================================================================
// useRecentCases Hook
// ============================================================================

interface UseRecentCasesReturn {
  cases: RecentCase[];
  loading: boolean;
  error: string | null;
  refetch: () => Promise<void>;
}

/**
 * Hook for recent cases
 */
export function useRecentCases(limit = 10): UseRecentCasesReturn {
  const [cases, setCases] = useState<RecentCase[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const fetchCases = useCallback(async () => {
    try {
      setLoading(true);
      setError(null);
      const data = await getRecentCases(limit);
      setCases(data);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to load recent cases');
    } finally {
      setLoading(false);
    }
  }, [limit]);

  useEffect(() => {
    fetchCases();
  }, [fetchCases]);

  return { cases, loading, error, refetch: fetchCases };
}

// ============================================================================
// usePatientFlow Hook
// ============================================================================

interface UsePatientFlowReturn {
  patientFlow: PatientFlow | null;
  loading: boolean;
  error: string | null;
  refetch: () => Promise<void>;
}

/**
 * Hook for patient flow metrics
 */
export function usePatientFlow(): UsePatientFlowReturn {
  const [patientFlow, setPatientFlow] = useState<PatientFlow | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const fetchFlow = useCallback(async () => {
    try {
      setLoading(true);
      setError(null);
      const data = await getPatientFlowMetrics();
      setPatientFlow(data);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to load patient flow metrics');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    fetchFlow();
  }, [fetchFlow]);

  return { patientFlow, loading, error, refetch: fetchFlow };
}

// ============================================================================
// useSitePerformance Hook
// ============================================================================

interface UseSitePerformanceReturn {
  sites: SitePerformance[];
  loading: boolean;
  error: string | null;
  refetch: () => Promise<void>;
}

/**
 * Hook for site performance metrics
 */
export function useSitePerformance(limit = 10): UseSitePerformanceReturn {
  const [sites, setSites] = useState<SitePerformance[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const fetchSites = useCallback(async () => {
    try {
      setLoading(true);
      setError(null);
      const data = await getSitePerformance(limit);
      setSites(data);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to load site performance');
    } finally {
      setLoading(false);
    }
  }, [limit]);

  useEffect(() => {
    fetchSites();
  }, [fetchSites]);

  return { sites, loading, error, refetch: fetchSites };
}

// ============================================================================
// useProviderPerformance Hook
// ============================================================================

interface UseProviderPerformanceReturn {
  providers: ProviderPerformance[];
  loading: boolean;
  error: string | null;
  refetch: () => Promise<void>;
}

/**
 * Hook for provider performance metrics
 */
export function useProviderPerformance(limit = 10): UseProviderPerformanceReturn {
  const [providers, setProviders] = useState<ProviderPerformance[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const fetchProviders = useCallback(async () => {
    try {
      setLoading(true);
      setError(null);
      const data = await getProviderPerformance(limit);
      setProviders(data);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to load provider performance');
    } finally {
      setLoading(false);
    }
  }, [limit]);

  useEffect(() => {
    fetchProviders();
  }, [fetchProviders]);

  return { providers, loading, error, refetch: fetchProviders };
}

// ============================================================================
// useStaffList Hook
// ============================================================================

interface UseStaffListReturn {
  staff: StaffMember[];
  pagination: {
    page: number;
    perPage: number;
    total: number;
    totalPages: number;
  };
  loading: boolean;
  error: string | null;
  refetch: () => Promise<void>;
  setPage: (page: number) => void;
}

/**
 * Hook for staff list with pagination
 */
export function useStaffList(initialPerPage = 50): UseStaffListReturn {
  const [staff, setStaff] = useState<StaffMember[]>([]);
  const [pagination, setPagination] = useState({
    page: 1,
    perPage: initialPerPage,
    total: 0,
    totalPages: 0,
  });
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const fetchStaff = useCallback(async () => {
    try {
      setLoading(true);
      setError(null);
      const result = await getStaffList({
        page: pagination.page,
        perPage: pagination.perPage,
      });
      setStaff(result.staff);
      setPagination(prev => ({
        ...prev,
        total: result.pagination.total,
        totalPages: result.pagination.total_pages,
      }));
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to load staff list');
    } finally {
      setLoading(false);
    }
  }, [pagination.page, pagination.perPage]);

  useEffect(() => {
    fetchStaff();
  }, [fetchStaff]);

  const setPage = useCallback((page: number) => {
    setPagination(prev => ({ ...prev, page }));
  }, []);

  return {
    staff,
    pagination,
    loading,
    error,
    refetch: fetchStaff,
    setPage,
  };
}

// ============================================================================
// useClearanceStats Hook
// ============================================================================

interface UseClearanceStatsReturn {
  clearanceStats: ClearanceStats | null;
  loading: boolean;
  error: string | null;
  refetch: () => Promise<void>;
}

/**
 * Hook for clearance statistics
 */
export function useClearanceStats(): UseClearanceStatsReturn {
  const [clearanceStats, setClearanceStats] = useState<ClearanceStats | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const fetchStats = useCallback(async () => {
    try {
      setLoading(true);
      setError(null);
      const data = await getClearanceStats();
      setClearanceStats(data);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to load clearance statistics');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    fetchStats();
  }, [fetchStats]);

  return { clearanceStats, loading, error, refetch: fetchStats };
}

// ============================================================================
// useComplianceAlerts Hook
// ============================================================================

interface UseComplianceAlertsReturn {
  alerts: ComplianceAlert[];
  pagination: {
    page: number;
    perPage: number;
    total: number;
    totalPages: number;
  };
  loading: boolean;
  error: string | null;
  refetch: () => Promise<void>;
  acknowledge: (alertId: string) => Promise<void>;
  setPage: (page: number) => void;
  setFilters: (filters: { status?: string; priority?: string }) => void;
}

/**
 * Hook for compliance alerts management
 */
export function useComplianceAlerts(): UseComplianceAlertsReturn {
  const [alerts, setAlerts] = useState<ComplianceAlert[]>([]);
  const [pagination, setPagination] = useState({
    page: 1,
    perPage: 20,
    total: 0,
    totalPages: 0,
  });
  const [filters, setFiltersState] = useState<{ status?: string; priority?: string }>({});
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const fetchAlerts = useCallback(async () => {
    try {
      setLoading(true);
      setError(null);
      const result = await getComplianceAlerts({
        ...filters,
        page: pagination.page,
        perPage: pagination.perPage,
      });
      setAlerts(result.alerts);
      setPagination(prev => ({
        ...prev,
        total: result.pagination.total,
        totalPages: result.pagination.total_pages,
      }));
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to load compliance alerts');
    } finally {
      setLoading(false);
    }
  }, [filters, pagination.page, pagination.perPage]);

  useEffect(() => {
    fetchAlerts();
  }, [fetchAlerts]);

  const acknowledge = useCallback(async (alertId: string) => {
    try {
      await acknowledgeComplianceAlert(alertId);
      // Update local state
      setAlerts(prev =>
        prev.map(alert =>
          alert.id === alertId
            ? { ...alert, status: 'acknowledged' as const }
            : alert
        )
      );
    } catch (err) {
      throw err;
    }
  }, []);

  const setPage = useCallback((page: number) => {
    setPagination(prev => ({ ...prev, page }));
  }, []);

  const setFilters = useCallback((newFilters: { status?: string; priority?: string }) => {
    setFiltersState(newFilters);
    setPagination(prev => ({ ...prev, page: 1 }));
  }, []);

  return {
    alerts,
    pagination,
    loading,
    error,
    refetch: fetchAlerts,
    acknowledge,
    setPage,
    setFilters,
  };
}

// ============================================================================
// useTrainingModules Hook
// ============================================================================

interface UseTrainingModulesReturn {
  modules: TrainingModule[];
  loading: boolean;
  error: string | null;
  refetch: () => Promise<void>;
}

/**
 * Hook for training modules summary
 */
export function useTrainingModules(): UseTrainingModulesReturn {
  const [modules, setModules] = useState<TrainingModule[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const fetchModules = useCallback(async () => {
    try {
      setLoading(true);
      setError(null);
      const data = await getTrainingModules();
      setModules(data);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to load training modules');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    fetchModules();
  }, [fetchModules]);

  return { modules, loading, error, refetch: fetchModules };
}

// ============================================================================
// useExpiringCredentials Hook
// ============================================================================

interface UseExpiringCredentialsReturn {
  credentials: ExpiringCredential[];
  count: number;
  loading: boolean;
  error: string | null;
  refetch: () => Promise<void>;
  setDaysAhead: (days: number) => void;
}

/**
 * Hook for expiring credentials
 */
export function useExpiringCredentials(initialDays = 60): UseExpiringCredentialsReturn {
  const [credentials, setCredentials] = useState<ExpiringCredential[]>([]);
  const [count, setCount] = useState(0);
  const [daysAhead, setDaysAhead] = useState(initialDays);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const fetchCredentials = useCallback(async () => {
    try {
      setLoading(true);
      setError(null);
      const data = await getExpiringCredentials(daysAhead);
      setCredentials(data.credentials);
      setCount(data.count);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to load expiring credentials');
    } finally {
      setLoading(false);
    }
  }, [daysAhead]);

  useEffect(() => {
    fetchCredentials();
  }, [fetchCredentials]);

  return {
    credentials,
    count,
    loading,
    error,
    refetch: fetchCredentials,
    setDaysAhead,
  };
}

// ============================================================================
// useOsha300Log Hook (READ-ONLY)
// ============================================================================

interface UseOsha300LogReturn {
  entries: Osha300Entry[];
  statistics: OshaStatistics | null;
  year: number;
  loading: boolean;
  error: string | null;
  refetch: () => Promise<void>;
  setYear: (year: number) => void;
}

/**
 * Hook for OSHA 300 Log (READ-ONLY per 29 CFR 1904)
 */
export function useOsha300Log(initialYear?: number): UseOsha300LogReturn {
  const [entries, setEntries] = useState<Osha300Entry[]>([]);
  const [statistics, setStatistics] = useState<OshaStatistics | null>(null);
  const [year, setYear] = useState(initialYear || new Date().getFullYear());
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const fetchLog = useCallback(async () => {
    try {
      setLoading(true);
      setError(null);
      const data = await getOsha300Log(year);
      setEntries(data.entries);
      setStatistics(data.statistics);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to load OSHA 300 Log');
    } finally {
      setLoading(false);
    }
  }, [year]);

  useEffect(() => {
    fetchLog();
  }, [fetchLog]);

  return {
    entries,
    statistics,
    year,
    loading,
    error,
    refetch: fetchLog,
    setYear,
  };
}

// ============================================================================
// useOsha300ASummary Hook (READ-ONLY)
// ============================================================================

interface UseOsha300ASummaryReturn {
  summary: Osha300AEntry | null;
  year: number;
  loading: boolean;
  error: string | null;
  refetch: () => Promise<void>;
  setYear: (year: number) => void;
}

/**
 * Hook for OSHA 300A Summary (READ-ONLY per 29 CFR 1904)
 */
export function useOsha300ASummary(initialYear?: number): UseOsha300ASummaryReturn {
  const [summary, setSummary] = useState<Osha300AEntry | null>(null);
  const [year, setYear] = useState(initialYear || new Date().getFullYear());
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const fetchSummary = useCallback(async () => {
    try {
      setLoading(true);
      setError(null);
      const data = await getOsha300ASummary(year);
      setSummary(data.summary);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to load OSHA 300A Summary');
    } finally {
      setLoading(false);
    }
  }, [year]);

  useEffect(() => {
    fetchSummary();
  }, [fetchSummary]);

  return {
    summary,
    year,
    loading,
    error,
    refetch: fetchSummary,
    setYear,
  };
}

// ============================================================================
// Export Default
// ============================================================================

export default {
  useAdminDashboard,
  useCaseStats,
  useRecentCases,
  usePatientFlow,
  useSitePerformance,
  useProviderPerformance,
  useStaffList,
  useClearanceStats,
  useComplianceAlerts,
  useTrainingModules,
  useExpiringCredentials,
  useOsha300Log,
  useOsha300ASummary,
};
