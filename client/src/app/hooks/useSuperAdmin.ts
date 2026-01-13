/**
 * SuperAdmin Dashboard Hooks
 *
 * React hooks for super admin functionality including user management,
 * clinics, audit logs, security incidents, and override requests.
 *
 * @module hooks/useSuperAdmin
 */

import { useState, useEffect, useCallback } from 'react';
import {
  getSuperAdminDashboard,
  getFullDashboard,
  getUsers,
  getUser,
  createUser,
  updateUserStatus,
  assignRole,
  removeRole,
  getRoles,
  getClinics,
  createClinic,
  getAuditLogs,
  getAuditStats,
  getSecurityIncidents,
  createSecurityIncident,
  resolveSecurityIncident,
  getOverrideRequests,
  approveOverrideRequest,
  denyOverrideRequest,
} from '../services/superadmin.service.js';
import type {
  SuperAdminStats,
  SystemUser,
  UserDetail,
  Role,
  Clinic,
  AuditLog,
  AuditStats,
  SecurityIncident,
  OverrideRequest,
  FullDashboardData,
} from '../services/superadmin.service.js';

// ============================================================================
// useSuperAdminDashboard Hook
// ============================================================================

interface UseSuperAdminDashboardReturn {
  stats: SuperAdminStats | null;
  loading: boolean;
  error: string | null;
  refetch: () => Promise<void>;
}

/**
 * Hook for super admin dashboard stats
 */
export function useSuperAdminDashboard(): UseSuperAdminDashboardReturn {
  const [stats, setStats] = useState<SuperAdminStats | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const fetchDashboard = useCallback(async () => {
    try {
      setLoading(true);
      setError(null);
      const data = await getSuperAdminDashboard();
      setStats(data);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to load dashboard');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    fetchDashboard();
  }, [fetchDashboard]);

  return { stats, loading, error, refetch: fetchDashboard };
}

// ============================================================================
// useUsers Hook
// ============================================================================

interface UseUsersReturn {
  users: SystemUser[];
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
  create: (userData: Parameters<typeof createUser>[0]) => Promise<string>;
  updateStatus: (userId: string, isActive: boolean) => Promise<void>;
}

/**
 * Hook for user management
 */
export function useUsers(): UseUsersReturn {
  const [users, setUsers] = useState<SystemUser[]>([]);
  const [pagination, setPagination] = useState({
    page: 1,
    perPage: 50,
    total: 0,
    totalPages: 0,
  });
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const fetchUsers = useCallback(async () => {
    try {
      setLoading(true);
      setError(null);
      const result = await getUsers({
        page: pagination.page,
        perPage: pagination.perPage,
      });
      setUsers(result.users);
      setPagination(prev => ({
        ...prev,
        total: result.pagination.total,
        totalPages: result.pagination.total_pages,
      }));
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to load users');
    } finally {
      setLoading(false);
    }
  }, [pagination.page, pagination.perPage]);

  useEffect(() => {
    fetchUsers();
  }, [fetchUsers]);

  const setPage = useCallback((page: number) => {
    setPagination(prev => ({ ...prev, page }));
  }, []);

  const create = useCallback(async (userData: Parameters<typeof createUser>[0]) => {
    const result = await createUser(userData);
    await fetchUsers();
    return result.userId;
  }, [fetchUsers]);

  const updateStatus = useCallback(async (userId: string, isActive: boolean) => {
    await updateUserStatus(userId, isActive);
    setUsers(prev =>
      prev.map(user =>
        user.id === userId ? { ...user, status: isActive ? 'active' : 'inactive' } : user
      )
    );
  }, []);

  return {
    users,
    pagination,
    loading,
    error,
    refetch: fetchUsers,
    setPage,
    create,
    updateStatus,
  };
}

// ============================================================================
// useUserDetail Hook
// ============================================================================

interface UseUserDetailReturn {
  user: UserDetail | null;
  loading: boolean;
  error: string | null;
  refetch: () => Promise<void>;
  assignUserRole: (roleId: string) => Promise<void>;
  removeUserRole: (roleId: string) => Promise<void>;
}

/**
 * Hook for user detail
 */
export function useUserDetail(userId: string | null): UseUserDetailReturn {
  const [user, setUser] = useState<UserDetail | null>(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const fetchUser = useCallback(async () => {
    if (!userId) return;

    try {
      setLoading(true);
      setError(null);
      const data = await getUser(userId);
      setUser(data);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to load user');
    } finally {
      setLoading(false);
    }
  }, [userId]);

  useEffect(() => {
    if (userId) {
      fetchUser();
    } else {
      setUser(null);
    }
  }, [userId, fetchUser]);

  const assignUserRole = useCallback(async (roleId: string) => {
    if (!userId) return;
    await assignRole(userId, roleId);
    await fetchUser();
  }, [userId, fetchUser]);

  const removeUserRole = useCallback(async (roleId: string) => {
    if (!userId) return;
    await removeRole(userId, roleId);
    await fetchUser();
  }, [userId, fetchUser]);

  return { user, loading, error, refetch: fetchUser, assignUserRole, removeUserRole };
}

// ============================================================================
// useRoles Hook
// ============================================================================

interface UseRolesReturn {
  roles: Role[];
  loading: boolean;
  error: string | null;
  refetch: () => Promise<void>;
}

/**
 * Hook for roles
 */
export function useRoles(): UseRolesReturn {
  const [roles, setRoles] = useState<Role[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const fetchRoles = useCallback(async () => {
    try {
      setLoading(true);
      setError(null);
      const data = await getRoles();
      setRoles(data);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to load roles');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    fetchRoles();
  }, [fetchRoles]);

  return { roles, loading, error, refetch: fetchRoles };
}

// ============================================================================
// useClinics Hook
// ============================================================================

interface UseClinicsReturn {
  clinics: Clinic[];
  loading: boolean;
  error: string | null;
  refetch: () => Promise<void>;
  create: (clinicData: Parameters<typeof createClinic>[0]) => Promise<string>;
}

/**
 * Hook for clinics
 */
export function useClinics(): UseClinicsReturn {
  const [clinics, setClinics] = useState<Clinic[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const fetchClinics = useCallback(async () => {
    try {
      setLoading(true);
      setError(null);
      const data = await getClinics();
      setClinics(data);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to load clinics');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    fetchClinics();
  }, [fetchClinics]);

  const create = useCallback(async (clinicData: Parameters<typeof createClinic>[0]) => {
    const result = await createClinic(clinicData);
    await fetchClinics();
    return result.clinicId;
  }, [fetchClinics]);

  return { clinics, loading, error, refetch: fetchClinics, create };
}

// ============================================================================
// useAuditLogs Hook
// ============================================================================

interface UseAuditLogsReturn {
  logs: AuditLog[];
  stats: AuditStats | null;
  loading: boolean;
  error: string | null;
  refetch: () => Promise<void>;
  setFilters: (filters: {
    userId?: string;
    action?: string;
    startDate?: string;
    endDate?: string;
  }) => void;
}

/**
 * Hook for audit logs
 */
export function useAuditLogs(): UseAuditLogsReturn {
  const [logs, setLogs] = useState<AuditLog[]>([]);
  const [stats, setStats] = useState<AuditStats | null>(null);
  const [filters, setFiltersState] = useState<{
    userId?: string;
    action?: string;
    startDate?: string;
    endDate?: string;
  }>({});
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const fetchLogs = useCallback(async () => {
    try {
      setLoading(true);
      setError(null);
      const [logsResult, statsResult] = await Promise.all([
        getAuditLogs(filters),
        getAuditStats(),
      ]);
      setLogs(logsResult.logs);
      setStats(statsResult);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to load audit logs');
    } finally {
      setLoading(false);
    }
  }, [filters]);

  useEffect(() => {
    fetchLogs();
  }, [fetchLogs]);

  const setFilters = useCallback((newFilters: typeof filters) => {
    setFiltersState(newFilters);
  }, []);

  return { logs, stats, loading, error, refetch: fetchLogs, setFilters };
}

// ============================================================================
// useSecurityIncidents Hook
// ============================================================================

interface UseSecurityIncidentsReturn {
  incidents: SecurityIncident[];
  loading: boolean;
  error: string | null;
  refetch: () => Promise<void>;
  create: (incidentData: Parameters<typeof createSecurityIncident>[0]) => Promise<string>;
  resolve: (incidentId: string, resolutionNotes: string) => Promise<void>;
  setStatusFilter: (status: string | null) => void;
}

/**
 * Hook for security incidents
 */
export function useSecurityIncidents(): UseSecurityIncidentsReturn {
  const [incidents, setIncidents] = useState<SecurityIncident[]>([]);
  const [statusFilter, setStatusFilter] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const fetchIncidents = useCallback(async () => {
    try {
      setLoading(true);
      setError(null);
      const data = await getSecurityIncidents(statusFilter ?? undefined);
      setIncidents(data);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to load security incidents');
    } finally {
      setLoading(false);
    }
  }, [statusFilter]);

  useEffect(() => {
    fetchIncidents();
  }, [fetchIncidents]);

  const create = useCallback(async (incidentData: Parameters<typeof createSecurityIncident>[0]) => {
    const result = await createSecurityIncident(incidentData);
    await fetchIncidents();
    return result.incidentId;
  }, [fetchIncidents]);

  const resolve = useCallback(async (incidentId: string, resolutionNotes: string) => {
    await resolveSecurityIncident(incidentId, resolutionNotes);
    setIncidents(prev =>
      prev.map(incident =>
        incident.id === incidentId
          ? { ...incident, status: 'resolved' as const, resolutionNotes }
          : incident
      )
    );
  }, []);

  return {
    incidents,
    loading,
    error,
    refetch: fetchIncidents,
    create,
    resolve,
    setStatusFilter,
  };
}

// ============================================================================
// useOverrideRequests Hook
// ============================================================================

interface UseOverrideRequestsReturn {
  requests: OverrideRequest[];
  pagination: {
    page: number;
    perPage: number;
    total: number;
    totalPages: number;
  };
  loading: boolean;
  error: string | null;
  refetch: () => Promise<void>;
  approve: (requestId: string, notes?: string) => Promise<void>;
  deny: (requestId: string, reason?: string) => Promise<void>;
  setStatusFilter: (status: string | null) => void;
  setPage: (page: number) => void;
}

/**
 * Hook for override requests
 */
export function useOverrideRequests(): UseOverrideRequestsReturn {
  const [requests, setRequests] = useState<OverrideRequest[]>([]);
  const [pagination, setPagination] = useState({
    page: 1,
    perPage: 50,
    total: 0,
    totalPages: 0,
  });
  const [statusFilter, setStatusFilter] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const fetchRequests = useCallback(async () => {
    try {
      setLoading(true);
      setError(null);
      const params: { status?: string; page?: number; perPage?: number } = {
        page: pagination.page,
        perPage: pagination.perPage,
      };
      if (statusFilter) {
        params.status = statusFilter;
      }
      const result = await getOverrideRequests(params);
      setRequests(result.overrideRequests);
      setPagination(prev => ({
        ...prev,
        total: result.pagination.total,
        totalPages: result.pagination.total_pages,
      }));
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to load override requests');
    } finally {
      setLoading(false);
    }
  }, [statusFilter, pagination.page, pagination.perPage]);

  useEffect(() => {
    fetchRequests();
  }, [fetchRequests]);

  const approve = useCallback(async (requestId: string, notes?: string) => {
    await approveOverrideRequest(requestId, notes);
    setRequests(prev =>
      prev.map(request =>
        request.id === requestId
          ? { ...request, status: 'approved' as const }
          : request
      )
    );
  }, []);

  const deny = useCallback(async (requestId: string, reason?: string) => {
    await denyOverrideRequest(requestId, reason);
    setRequests(prev =>
      prev.map(request =>
        request.id === requestId
          ? { ...request, status: 'denied' as const }
          : request
      )
    );
  }, []);

  const setPage = useCallback((page: number) => {
    setPagination(prev => ({ ...prev, page }));
  }, []);

  return {
    requests,
    pagination,
    loading,
    error,
    refetch: fetchRequests,
    approve,
    deny,
    setStatusFilter,
    setPage,
  };
}

// ============================================================================
// useFullDashboard Hook
// ============================================================================

interface UseFullDashboardReturn {
  data: FullDashboardData | null;
  loading: boolean;
  error: string | null;
  refetch: () => Promise<void>;
}

/**
 * Hook for full dashboard data
 */
export function useFullDashboard(): UseFullDashboardReturn {
  const [data, setData] = useState<FullDashboardData | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const fetchDashboard = useCallback(async () => {
    try {
      setLoading(true);
      setError(null);
      const result = await getFullDashboard();
      setData(result);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to load dashboard');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    fetchDashboard();
  }, [fetchDashboard]);

  return { data, loading, error, refetch: fetchDashboard };
}

// ============================================================================
// Export Default
// ============================================================================

export default {
  useSuperAdminDashboard,
  useFullDashboard,
  useUsers,
  useUserDetail,
  useRoles,
  useClinics,
  useAuditLogs,
  useSecurityIncidents,
  useOverrideRequests,
};
